<?php

namespace App\Learning\LaravelInterview\Console;

use App\Learning\LaravelInterview\Support\PhpLanguageFeatureExamples;
use Illuminate\Console\Command;

class InterviewPhpFeaturesCommand extends Command
{
    protected $signature = 'interview:php-features
        {--json : Output the complete payload as JSON}';

    protected $description = '演示 PHP 8.1-8.5 新特性、版本门控和资深面试追问点。';

    public function handle(PhpLanguageFeatureExamples $examples): int
    {
        $payload = $examples->run();

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('PHP language feature interview examples');

        $this->table(['Runtime', 'Value'], [
            ['PHP Version', $payload['runtime']['php_version']],
            ['PHP Version ID', (string) $payload['runtime']['php_version_id']],
            ['SAPI', $payload['runtime']['sapi']],
        ]);

        $this->table(['Version', 'Status', 'Covered topics'], $this->versionRows($payload));

        $this->line('PHP 8.1 enum: '.$payload['php_81']['examples']['enum']['value'].' / '.$payload['php_81']['examples']['enum']['label']);
        $this->line('PHP 8.2 Randomizer: '.json_encode($payload['php_82']['examples']['randomizer'] ?? $payload['php_82']['examples'], JSON_UNESCAPED_UNICODE));
        $this->line('PHP 8.3 typed constant: '.json_encode($payload['php_83']['examples']['typed_class_constant_and_dynamic_fetch'] ?? $payload['php_83']['examples'], JSON_UNESCAPED_UNICODE));
        $this->line('PHP 8.4 status: '.$payload['php_84']['status']);
        $this->line('PHP 8.5 status: '.$payload['php_85']['status']);

        foreach ($payload['interview_points'] as $point) {
            $this->line('- '.$point);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{string, string, string}>
     */
    private function versionRows(array $payload): array
    {
        $versions = [
            'php_81' => '8.1',
            'php_82' => '8.2',
            'php_83' => '8.3',
            'php_84' => '8.4',
            'php_85' => '8.5',
        ];

        $rows = [];

        foreach ($versions as $key => $version) {
            $examples = $payload[$key]['examples'] ?? [];

            if (isset($examples['features']) && is_array($examples['features'])) {
                $topics = implode(', ', $examples['features']);
            } else {
                $topics = implode(', ', array_keys($examples));
            }

            $rows[] = [
                $version,
                (string) ($payload[$key]['status'] ?? 'unknown'),
                $topics,
            ];
        }

        return $rows;
    }
}
