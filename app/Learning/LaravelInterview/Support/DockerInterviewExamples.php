<?php

namespace App\Learning\LaravelInterview\Support;

class DockerInterviewExamples
{
    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return [
            'project_files' => [
                'compose_file' => [
                    'path' => 'docker-compose.yml',
                    'exists' => file_exists(base_path('docker-compose.yml')),
                    'purpose' => '编排 PHP、MySQL、Redis、RabbitMQ、Mailpit 本地学习环境。',
                ],
                'php_dockerfile' => [
                    'path' => 'docker/php/8.3/Dockerfile',
                    'exists' => file_exists(base_path('docker/php/8.3/Dockerfile')),
                    'purpose' => '构建 PHP 8.3 + 扩展 + Composer 的 Laravel 运行镜像。',
                ],
                'dockerignore' => [
                    'path' => '.dockerignore',
                    'exists' => file_exists(base_path('.dockerignore')),
                    'purpose' => '减少构建上下文，避免 vendor、node_modules、日志等进入镜像构建。',
                ],
            ],
            'services' => [
                'laravel.test' => [
                    'role' => 'PHP 8.3 Laravel application container',
                    'ports' => ['8000:80'],
                    'depends_on' => ['mysql', 'redis', 'rabbitmq', 'mailpit'],
                    'interview_point' => '容器内访问其他服务要用 compose service name，不是 127.0.0.1。',
                ],
                'mysql' => [
                    'role' => 'MySQL 8 database',
                    'ports' => ['3306:3306'],
                    'volume' => 'mysql_data',
                    'interview_point' => '数据库 volume 保证容器重建后数据仍在，down -v 才会删除。',
                ],
                'redis' => [
                    'role' => 'Redis 7 cache and queue backend',
                    'ports' => ['6379:6379'],
                    'volume' => 'redis_data',
                    'interview_point' => 'Redis 健康检查不能只看容器存活，应确认 PING 或业务读写。',
                ],
                'rabbitmq' => [
                    'role' => 'RabbitMQ broker with management UI',
                    'ports' => ['5672:5672', '15672:15672'],
                    'volume' => 'rabbitmq_data',
                    'interview_point' => '管理端口和业务 AMQP 端口要区分，生产环境不要直接暴露管理后台。',
                ],
                'mailpit' => [
                    'role' => 'Local mail catcher',
                    'ports' => ['8025:8025'],
                    'volume' => null,
                    'interview_point' => '本地邮件捕获避免开发环境误发真实邮件。',
                ],
            ],
            'commands' => [
                'build' => 'docker compose build laravel.test',
                'start' => 'docker compose up -d',
                'rebuild' => 'docker compose up -d --build',
                'status' => 'docker compose ps',
                'logs' => 'docker compose logs -f laravel.test',
                'shell' => 'docker compose exec laravel.test bash',
                'migrate' => 'docker compose exec laravel.test php artisan migrate',
                'stop_keep_data' => 'docker compose down',
                'stop_remove_data' => 'docker compose down -v',
            ],
            'production_topics' => [
                'image_size' => '用 .dockerignore、多阶段构建、固定基础镜像版本减少体积和不确定性。',
                'secrets' => '生产密钥不写入镜像层，使用环境变量、secret manager 或平台 secret。',
                'permissions' => 'storage/bootstrap cache 权限要给运行用户，避免容器启动后写入失败。',
                'healthcheck' => '健康检查应覆盖应用入口和关键依赖，不只看进程是否存在。',
                'logs' => '容器日志输出到 stdout/stderr，交给平台采集和轮转。',
                'deploy' => '生产通常是 Nginx + PHP-FPM 或独立应用镜像，数据库迁移要按发布顺序控制。',
            ],
            'interview_points' => [
                '镜像是只读模板，容器是镜像运行后的进程实例。',
                'volume 解决持久化，network 解决服务发现和容器互联。',
                '容器内 127.0.0.1 指向当前容器，不是宿主机或其他服务。',
                '本地开发 compose 和生产镜像目标不同，生产要关注镜像体积、安全、启动速度和可观测性。',
                'Docker 不能替代配置管理、密钥管理、数据库迁移和回滚策略。',
            ],
        ];
    }
}
