<?php

namespace App\Providers;

use App\Domains\Access\Models\Role;
use App\Domains\Dashboard\Observers\RefreshDashboardSummaryObserver;
use App\Domains\Imports\Models\ImportTask;
use App\Domains\Messaging\Clients\DockerKafkaClient;
use App\Domains\Messaging\Clients\LocalKafkaClient;
use App\Domains\Messaging\Contracts\KafkaClient;
use App\Domains\Messaging\Handlers\AuditLogEventHandler;
use App\Domains\Messaging\Handlers\MetricCacheRefreshHandler;
use App\Domains\Messaging\KafkaConsumerService;
use App\Domains\Messaging\KafkaMessageFactory;
use App\Domains\Metrics\Models\Metric;
use App\Events\AuditEvent;
use App\Listeners\WriteAuditLog;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

// 学习要点：AppServiceProvider 是应用自己的默认启动扩展点。
// 如果要绑定接口实现、注册宏、配置模型约束或做全局初始化，通常从这里开始。
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        // register() 只负责“登记服务”，例如：
        // $this->app->bind(PaymentGateway::class, StripeGateway::class);
        // 面试重点：register 阶段应尽量避免使用还未 boot 的其他服务。
        $this->app->singleton(KafkaClient::class, function (Application $app): KafkaClient {
            if (config('kafka.driver') === 'docker') {
                return new DockerKafkaClient(
                    composeCommand: config('kafka.docker.compose', ['docker', 'compose']),
                    service: (string) config('kafka.docker.service', 'kafka'),
                    brokers: (string) config('kafka.brokers', 'kafka:9092'),
                    timeout: (int) config('kafka.docker.timeout', 30),
                );
            }

            return new LocalKafkaClient(
                files: $app->make(Filesystem::class),
                basePath: (string) config('kafka.local_path', storage_path('app/kafka')),
            );
        });

        $this->app->tag([
            AuditLogEventHandler::class,
            MetricCacheRefreshHandler::class,
        ], 'kafka.handlers');

        $this->app->singleton(KafkaConsumerService::class, function (Application $app): KafkaConsumerService {
            return new KafkaConsumerService(
                client: $app->make(KafkaClient::class),
                factory: $app->make(KafkaMessageFactory::class),
                handlers: $app->tagged('kafka.handlers'),
                maxAttempts: (int) config('kafka.retry.max_attempts', 3),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // boot() 在所有 provider 注册完成后运行。
        // 适合注册事件监听、Blade 指令、路由模型绑定、Schema 默认长度、模型全局 scope 等。
        Event::listen(AuditEvent::class, WriteAuditLog::class);

        User::observe(RefreshDashboardSummaryObserver::class);
        Role::observe(RefreshDashboardSummaryObserver::class);
        Metric::observe(RefreshDashboardSummaryObserver::class);
        ImportTask::observe(RefreshDashboardSummaryObserver::class);

        RateLimiter::for('metrics-query', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });
    }
}
