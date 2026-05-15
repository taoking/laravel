<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhaseFourteenDockerRunbookTest extends TestCase
{
    public function test_docker_compose_configuration_is_valid(): void
    {
        $process = proc_open(
            'docker compose config',
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            base_path(),
        );

        $this->assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, $stderr);
        $this->assertStringContainsString('laravel', $stdout);
        $this->assertStringContainsString('queue', $stdout);
        $this->assertStringContainsString('scheduler', $stdout);
        $this->assertStringContainsString('healthcheck', $stdout);
        $this->assertStringContainsString('restart: unless-stopped', $stdout);
        $this->assertStringContainsString('node-modules', $stdout);
    }

    public function test_docker_smoke_script_documents_runtime_checks(): void
    {
        $script = (string) file_get_contents(base_path('scripts/deploy/docker-smoke.sh'));

        $this->assertStringContainsString('docker compose', $script);
        $this->assertStringContainsString('up -d --build mysql redis kafka app nginx queue scheduler', $script);
        $this->assertStringContainsString('safe.directory /var/www/html', $script);
        $this->assertStringContainsString('force-recreate nginx', $script);
        $this->assertStringContainsString('wait_for_http', $script);
        $this->assertStringContainsString('wait_for_container_health', $script);
        $this->assertStringContainsString('.State.Health.Status', $script);
        $this->assertStringContainsString('php artisan migrate --seed --force', $script);
        $this->assertStringContainsString('php artisan queue:restart', $script);
        $this->assertStringContainsString('kafka:topics --create', $script);
        $this->assertStringContainsString('kafka-topics.sh', $script);
        $this->assertStringContainsString('/up', $script);
        $this->assertStringContainsString('/login', $script);
        $this->assertStringContainsString('/docs/api', $script);
        $this->assertStringContainsString('/api/v1/health', $script);
    }

    public function test_php_docker_image_contains_runtime_dependencies(): void
    {
        $dockerfile = (string) file_get_contents(base_path('docker/php/Dockerfile'));

        $this->assertStringContainsString('nodejs npm', $dockerfile);
        $this->assertStringContainsString('libxml2-dev', $dockerfile);
        $this->assertStringContainsString('pecl install redis', $dockerfile);
        $this->assertStringContainsString('docker-php-ext-enable redis', $dockerfile);
        $this->assertStringContainsString('dom intl mbstring opcache pdo_mysql xml xmlreader xmlwriter zip', $dockerfile);
    }

    public function test_nginx_resolves_recreated_app_container(): void
    {
        $nginx = (string) file_get_contents(base_path('docker/nginx/default.conf'));

        $this->assertStringContainsString('resolver 127.0.0.11', $nginx);
        $this->assertStringContainsString('set $php_fpm app:9000', $nginx);
        $this->assertStringContainsString('fastcgi_pass $php_fpm', $nginx);
    }

    public function test_docker_runbook_contains_production_troubleshooting_paths(): void
    {
        $runbook = (string) file_get_contents(base_path('docs/deploy/docker-deploy-runbook.md'));

        $this->assertStringContainsString('scripts/deploy/docker-smoke.sh', $runbook);
        $this->assertStringContainsString('restart: unless-stopped', $runbook);
        $this->assertStringContainsString('phpredis', $runbook);
        $this->assertStringContainsString('node-modules', $runbook);
        $this->assertStringContainsString('502 Bad Gateway', $runbook);
        $this->assertStringContainsString('504 Gateway Timeout', $runbook);
        $this->assertStringContainsString('Redis 队列不消费', $runbook);
        $this->assertStringContainsString('Scheduler 重复执行', $runbook);
        $this->assertStringContainsString('Kafka 不可达', $runbook);
        $this->assertStringContainsString('queue:restart', $runbook);
    }
}
