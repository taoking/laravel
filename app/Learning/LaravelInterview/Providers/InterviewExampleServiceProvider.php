<?php

namespace App\Learning\LaravelInterview\Providers;

use App\Learning\LaravelInterview\Console\InterviewDigestCommand;
use App\Learning\LaravelInterview\Console\InterviewDockerCommand;
use App\Learning\LaravelInterview\Console\InterviewInfrastructureCheckCommand;
use App\Learning\LaravelInterview\Console\InterviewMqCompareCommand;
use App\Learning\LaravelInterview\Console\InterviewMySqlCommand;
use App\Learning\LaravelInterview\Console\InterviewPhpFeaturesCommand;
use App\Learning\LaravelInterview\Console\InterviewPhpRuntimeCommand;
use App\Learning\LaravelInterview\Console\InterviewProcessCommand;
use App\Learning\LaravelInterview\Console\InterviewQueueCommand;
use App\Learning\LaravelInterview\Console\InterviewRabbitMqCommand;
use App\Learning\LaravelInterview\Console\InterviewRedisCommand;
use App\Learning\LaravelInterview\Console\InterviewSecurityCommand;
use App\Learning\LaravelInterview\Console\InterviewSystemDesignCommand;
use App\Learning\LaravelInterview\Console\InterviewTroubleshootCommand;
use App\Learning\LaravelInterview\Contracts\PaymentGateway;
use App\Learning\LaravelInterview\Events\OrderPaid;
use App\Learning\LaravelInterview\Listeners\WriteOrderPaidAuditLog;
use App\Learning\LaravelInterview\Services\FakePaymentGateway;
use App\Learning\LaravelInterview\Support\LaravelComponentCheatsheet;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class InterviewExampleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // bind：每次解析都创建新实例；常用于轻量、无状态服务。
        $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);

        // singleton：整个应用生命周期共享一个实例；适合配置型、无请求状态的服务。
        $this->app->singleton(LaravelComponentCheatsheet::class);

        // scoped：同一次请求或队列任务内共享；Octane/Swoole 场景比 singleton 更安全。
        $this->app->scoped('interview.request_id', fn (): string => (string) Str::uuid());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InterviewDigestCommand::class,
                InterviewDockerCommand::class,
                InterviewInfrastructureCheckCommand::class,
                InterviewMqCompareCommand::class,
                InterviewMySqlCommand::class,
                InterviewPhpFeaturesCommand::class,
                InterviewPhpRuntimeCommand::class,
                InterviewProcessCommand::class,
                InterviewQueueCommand::class,
                InterviewRabbitMqCommand::class,
                InterviewRedisCommand::class,
                InterviewSecurityCommand::class,
                InterviewSystemDesignCommand::class,
                InterviewTroubleshootCommand::class,
            ]);
        }

        if (! config('interview_examples.enabled')) {
            return;
        }

        Gate::define('view-interview-examples', fn (?User $user): bool => $user !== null);

        RateLimiter::for('interview-api', function (Request $request): Limit {
            $identity = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(30)->by((string) $identity);
        });

        Event::listen(OrderPaid::class, WriteOrderPaidAuditLog::class);

        Response::macro('interviewOk', function (array $data = []) {
            return response()->json([
                'ok' => true,
                'data' => $data,
            ]);
        });
    }
}
