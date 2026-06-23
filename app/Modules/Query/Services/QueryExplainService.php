<?php

namespace App\Modules\Query\Services;

use App\Models\User;
use App\Modules\Chart\Models\Chart;
use App\Modules\Chart\Services\ChartConfigValidator;
use App\Modules\Chart\Services\ChartQueryBuilder;
use App\Modules\DataPermission\Services\DataPermissionService;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\DataSource\Services\DataSourceMetadataService;
use App\Modules\Query\Compilers\SqlCompiler;
use App\Modules\Query\DTO\QueryRequestDTO;
use App\Modules\Query\Validators\QueryRequestValidator;
use Illuminate\Validation\ValidationException;
use Throwable;

class QueryExplainService
{
    public function __construct(
        private readonly DataPermissionService $dataPermissionService,
        private readonly QueryRequestValidator $validator,
        private readonly SqlCompiler $sqlCompiler,
        private readonly DataSourceMetadataService $metadataService,
        private readonly ChartConfigValidator $chartConfigValidator,
        private readonly ChartQueryBuilder $chartQueryBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function dataset(Dataset $dataset, array $payload, ?User $user): array
    {
        $dataset->loadMissing(['dataSource', 'fields']);
        $query = QueryRequestDTO::fromArray([
            ...$payload,
            'dataset_id' => $dataset->id,
            'use_cache' => false,
        ]);

        $this->dataPermissionService->assertCanAccessDataset($dataset, $user);
        $this->validator->validate($dataset, $query, $user);

        $compiledQuery = $this->sqlCompiler->compile($dataset, $query, $user);
        $startedAt = microtime(true);
        $warnings = [];
        $explainResult = [];

        try {
            $explainResult = $this->metadataService->explain($dataset->dataSource, $compiledQuery->sql, $compiledQuery->bindings);
        } catch (Throwable $exception) {
            $warnings[] = $exception->getMessage();
        }

        return [
            'generated_sql' => $compiledQuery->sql,
            'bindings' => $compiledQuery->bindings,
            'query_hash' => $compiledQuery->hash,
            'engine_type' => $dataset->dataSource?->type,
            'data_source_type' => $dataset->dataSource?->type,
            'data_source_id' => $dataset->dataSource?->id,
            'explain_result' => $explainResult,
            'estimated_info' => null,
            'warnings' => $warnings,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function chart(Chart $chart, array $overrides, ?User $user): array
    {
        if ($chart->status !== 'active') {
            throw ValidationException::withMessages([
                'chart' => ['The selected chart is disabled.'],
            ]);
        }

        $chart->loadMissing(['dataset.dataSource', 'dataset.fields']);
        $this->chartConfigValidator->validate($chart->dataset, $chart->chart_type, $chart->config_json);

        return [
            ...$this->dataset(
                $chart->dataset,
                $this->chartQueryBuilder->build($chart->dataset_id, $chart->config_json, $overrides),
                $user,
            ),
            'chart_id' => $chart->id,
        ];
    }
}
