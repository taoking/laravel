<?php

namespace App\Learning\LaravelInterview\Console;

use App\Learning\LaravelInterview\Support\DockerInterviewExamples;
use Illuminate\Console\Command;

class InterviewDockerCommand extends Command
{
    protected $signature = 'interview:docker
        {--json : Output the complete payload as JSON}';

    protected $description = '演示 Docker Compose、本地服务编排、volume/network/healthcheck 和部署面试点。';

    public function handle(DockerInterviewExamples $examples): int
    {
        $payload = $examples->run();

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Docker interview examples');

        $this->table(['File', 'Exists', 'Purpose'], array_map(
            fn (array $file): array => [
                $file['path'],
                $file['exists'] ? 'yes' : 'no',
                $file['purpose'],
            ],
            $payload['project_files'],
        ));

        $this->table(['Service', 'Role', 'Ports'], array_map(
            fn (string $name, array $service): array => [
                $name,
                $service['role'],
                implode(', ', $service['ports']),
            ],
            array_keys($payload['services']),
            $payload['services'],
        ));

        foreach ($payload['commands'] as $label => $command) {
            $this->line($label.': '.$command);
        }

        foreach ($payload['interview_points'] as $point) {
            $this->line('- '.$point);
        }

        return self::SUCCESS;
    }
}
