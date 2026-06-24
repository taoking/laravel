<?php

namespace App\Modules\Metadata\Services;

use App\Models\User;
use App\Modules\Dashboard\Models\DashboardShare;
use App\Modules\Metadata\Models\ImpactAnalysisLog;
use App\Modules\Metadata\Models\MetadataAsset;
use App\Modules\Metadata\Models\MetadataAssetTag;
use Illuminate\Support\Collection;

class MetadataImpactAnalysisService
{
    public const CHANGE_TYPES = ['delete', 'update', 'deprecate', 'archive', 'schema_change'];

    public function __construct(
        private readonly MetadataAssetService $assetService,
        private readonly MetadataLineageService $lineageService,
        private readonly MetadataAuthorizer $authorizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $assetType, int $assetId, string $changeType, ?User $user, bool $log = true): array
    {
        $asset = $this->assetService->find($assetType, $assetId, $user);
        $graph = $this->lineageService->downstream($assetType, $assetId, 5);
        $nodes = collect($graph['nodes'])
            ->map(fn (array $node): ?MetadataAsset => MetadataAsset::query()
                ->where('asset_type', $node['type'])
                ->where('asset_id', $node['asset_id'])
                ->first())
            ->filter(fn (?MetadataAsset $node): bool => $node instanceof MetadataAsset && $this->authorizer->canViewAsset($node, $user))
            ->values();

        $affected = [
            'affected_datasets' => $this->assetsOfType($nodes, 'dataset'),
            'affected_dataset_fields' => $this->assetsOfType($nodes, 'dataset_field'),
            'affected_dimensions' => $this->assetsOfType($nodes, 'dimension'),
            'affected_metrics' => $this->assetsOfType($nodes, 'metric'),
            'affected_charts' => $this->assetsOfType($nodes, 'chart'),
            'affected_dashboards' => $this->assetsOfType($nodes, 'dashboard'),
            'affected_acceleration_profiles' => $this->assetsOfType($nodes, 'acceleration_profile'),
            'affected_aggregate_definitions' => $this->assetsOfType($nodes, 'aggregate_definition'),
        ];

        $publicShareCount = DashboardShare::query()
            ->whereIn('dashboard_id', collect($affected['affected_dashboards'])->pluck('asset_id')->all())
            ->where(function ($query): void {
                $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })
            ->count();
        $coreMetricCount = $this->coreMetricCount(collect($affected['affected_metrics'])->pluck('asset_id')->all());
        $riskLevel = $this->riskLevel(
            chartCount: count($affected['affected_charts']),
            dashboardCount: count($affected['affected_dashboards']),
            publicShareCount: $publicShareCount,
            coreMetricCount: $coreMetricCount,
        );

        $result = [
            'asset' => $this->assetPayload($asset),
            'change_type' => $changeType,
            'risk_level' => $riskLevel,
            ...$affected,
            'public_share_count' => $publicShareCount,
            'core_metric_count' => $coreMetricCount,
            'suggestions' => $this->suggestions($riskLevel, $changeType, $affected, $publicShareCount, $coreMetricCount),
            'lineage' => [
                'relations' => $graph['relations'],
                'nodes' => $graph['nodes'],
            ],
        ];

        if ($log) {
            ImpactAnalysisLog::query()->create([
                'asset_type' => $assetType,
                'asset_id' => $assetId,
                'change_type' => $changeType,
                'impact_result_json' => $result,
                'risk_level' => $riskLevel,
                'analyzed_by' => $user?->id,
            ]);
        }

        return $result;
    }

    private function riskLevel(int $chartCount, int $dashboardCount, int $publicShareCount, int $coreMetricCount): string
    {
        if ($publicShareCount > 0 || $coreMetricCount > 0 || $chartCount > 10) {
            return 'critical';
        }

        if ($chartCount >= 4 || $dashboardCount > 1) {
            return 'high';
        }

        if ($chartCount >= 1 || $dashboardCount === 1) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  Collection<int, MetadataAsset>  $nodes
     * @return list<array<string, mixed>>
     */
    private function assetsOfType($nodes, string $assetType): array
    {
        return $nodes
            ->where('asset_type', $assetType)
            ->unique(fn (MetadataAsset $asset): string => $asset->asset_type.':'.$asset->asset_id)
            ->map(fn (MetadataAsset $asset): array => $this->assetPayload($asset))
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $metricIds
     */
    private function coreMetricCount(array $metricIds): int
    {
        if ($metricIds === []) {
            return 0;
        }

        return MetadataAssetTag::query()
            ->join('metadata_tags', 'metadata_tags.id', '=', 'metadata_asset_tags.tag_id')
            ->where('metadata_asset_tags.asset_type', 'metric')
            ->whereIn('metadata_asset_tags.asset_id', $metricIds)
            ->where(function ($query): void {
                $query->where('metadata_tags.name', '核心指标')
                    ->orWhere('metadata_tags.name', 'core')
                    ->orWhere('metadata_tags.name', '核心资产');
            })
            ->distinct('metadata_asset_tags.asset_id')
            ->count('metadata_asset_tags.asset_id');
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $affected
     * @return list<string>
     */
    private function suggestions(string $riskLevel, string $changeType, array $affected, int $publicShareCount, int $coreMetricCount): array
    {
        $suggestions = [];

        if ($riskLevel === 'low') {
            $suggestions[] = '未发现可见下游依赖，仍建议在变更后重新同步元数据和血缘。';
        }

        if (in_array($riskLevel, ['medium', 'high', 'critical'], true)) {
            $suggestions[] = '变更前请通知受影响资产负责人，并准备回滚或替代字段方案。';
        }

        if ($changeType === 'delete') {
            $suggestions[] = '删除前建议先将相关字段、指标、图表或仪表盘迁移到替代资产。';
        }

        if ($publicShareCount > 0) {
            $suggestions[] = '存在公开分享仪表盘，请先确认外部访问影响范围。';
        }

        if ($coreMetricCount > 0) {
            $suggestions[] = '存在核心指标依赖，请走指标口径变更确认流程。';
        }

        if (count($affected['affected_charts']) > 0 || count($affected['affected_dashboards']) > 0) {
            $suggestions[] = '变更后请重新校验受影响图表和仪表盘的数据结果。';
        }

        return array_values(array_unique($suggestions));
    }

    /**
     * @return array<string, mixed>
     */
    private function assetPayload(MetadataAsset $asset): array
    {
        return [
            'asset_type' => $asset->asset_type,
            'asset_id' => $asset->asset_id,
            'name' => $asset->name,
            'code' => $asset->code,
            'status' => $asset->status,
            'dataset_id' => $asset->dataset_id,
            'data_source_id' => $asset->data_source_id,
        ];
    }
}
