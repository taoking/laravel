<?php

namespace App\Learning\LaravelInterview\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class InterviewInfrastructureCheckCommand extends Command
{
    protected $signature = 'interview:infra-check
        {--timeout=2 : TCP check timeout in seconds}';

    protected $description = '检查学习环境中的 MySQL、Redis、RabbitMQ、Mailpit 连通性。';

    public function handle(): int
    {
        $timeout = max(1, (int) $this->option('timeout'));

        $rows = [
            $this->check('MySQL', function (): string {
                $version = DB::selectOne('select version() as version')->version ?? 'unknown';

                return "connected, version {$version}";
            }),
            $this->check('Redis', function (): string {
                $pong = Redis::connection()->ping();

                return 'ping '.($pong === true ? 'PONG' : (string) $pong);
            }),
            $this->check('RabbitMQ', fn (): string => $this->checkTcp(
                (string) config('interview_examples.infrastructure.rabbitmq.host'),
                (int) config('interview_examples.infrastructure.rabbitmq.port'),
                $timeout,
            )),
            $this->check('Mailpit', fn (): string => $this->checkTcp(
                (string) config('interview_examples.infrastructure.mailpit.host'),
                (int) config('interview_examples.infrastructure.mailpit.port'),
                $timeout,
            )),
        ];

        $this->table(['Service', 'Status', 'Detail'], $rows);

        return collect($rows)->contains(fn (array $row): bool => $row[1] !== 'ok')
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function check(string $service, Closure $callback): array
    {
        try {
            return [$service, 'ok', $this->shorten((string) $callback())];
        } catch (Throwable $exception) {
            return [$service, 'failed', $this->shorten($exception->getMessage())];
        }
    }

    private function checkTcp(string $host, int $port, int $timeout): string
    {
        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, $timeout);

        if ($socket === false) {
            throw new \RuntimeException("{$host}:{$port} {$errorCode} {$errorMessage}");
        }

        fclose($socket);

        return "tcp {$host}:{$port} reachable";
    }

    private function shorten(string $value): string
    {
        return strlen($value) > 160 ? substr($value, 0, 157).'...' : $value;
    }
}
