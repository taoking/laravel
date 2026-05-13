<?php

namespace App\Learning\LaravelInterview\Console;

use App\Learning\LaravelInterview\Support\ProductionTroubleshootingPlaybook;
use Illuminate\Console\Command;

class InterviewTroubleshootCommand extends Command
{
    protected $signature = 'interview:troubleshoot
        {scenario? : Optional scenario key, for example mysql-slow-query}
        {--json : Output the payload as JSON}';

    protected $description = '输出线上故障、性能问题和资深面试复盘 playbook。';

    public function handle(ProductionTroubleshootingPlaybook $playbook): int
    {
        $scenario = $this->argument('scenario');

        if (is_string($scenario) && $scenario !== '') {
            $payload = $playbook->scenario($scenario);

            if ($payload === null) {
                $this->error("Unknown scenario: {$scenario}");
                $this->line('Available scenarios: '.implode(', ', $playbook->keys()));

                return self::FAILURE;
            }

            return $this->renderScenario($scenario, $payload);
        }

        $payload = $playbook->all();

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Production troubleshooting interview playbook');
        $this->table(['Scenario', 'Title', 'First checks'], array_map(
            fn (string $key, array $item): array => [
                $key,
                $item['title'],
                implode(' | ', $item['first_checks']),
            ],
            array_keys($payload),
            $payload,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderScenario(string $key, array $payload): int
    {
        if ($this->option('json')) {
            $this->line(json_encode([$key => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info($payload['title']);
        $this->line('Scenario: '.$key);

        foreach (['symptoms', 'first_checks', 'likely_causes', 'remediation', 'prevention'] as $section) {
            $this->newLine();
            $this->line(str_replace('_', ' ', strtoupper($section)));

            foreach ($payload[$section] as $item) {
                $this->line('- '.$item);
            }
        }

        $this->newLine();
        $this->line('Interview answer: '.$payload['interview_answer']);

        return self::SUCCESS;
    }
}
