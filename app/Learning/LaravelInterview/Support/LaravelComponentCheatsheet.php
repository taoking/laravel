<?php

namespace App\Learning\LaravelInterview\Support;

use App\Learning\LaravelInterview\Contracts\PaymentGateway;
use App\Learning\LaravelInterview\Events\OrderPaid;
use App\Learning\LaravelInterview\Jobs\SendInterviewWelcomeMail;
use App\Learning\LaravelInterview\Models\Post;
use App\Learning\LaravelInterview\Services\FakePaymentGateway;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LaravelComponentCheatsheet
{
    /**
     * 面试点：Collection 是 Laravel 中最常见的数据管道工具，适合链式处理内存数组。
     *
     * @return array<string, mixed>
     */
    public function collectionExamples(): array
    {
        $orders = collect([
            ['id' => 1, 'status' => 'paid', 'total' => 120, 'customer' => ['name' => 'Ada']],
            ['id' => 2, 'status' => 'pending', 'total' => 80, 'customer' => ['name' => 'Linus']],
            ['id' => 3, 'status' => 'paid', 'total' => 260, 'customer' => ['name' => 'Taylor']],
        ]);

        return [
            'paid_total' => $orders->where('status', 'paid')->sum('total'),
            'count_by_status' => $orders->groupBy('status')->map->count()->all(),
            'customer_names' => $orders->pluck('customer.name')->values()->all(),
            'high_value_paid_orders' => $orders
                ->filter(fn (array $order): bool => $order['status'] === 'paid' && $order['total'] >= 100)
                ->map(fn (array $order): array => Arr::only($order, ['id', 'total']))
                ->sortByDesc('total')
                ->values()
                ->all(),
        ];
    }

    /**
     * 面试点：服务容器负责依赖解析，接口绑定让 Controller/Service 不依赖具体实现。
     *
     * @return array<string, mixed>
     */
    public function containerExamples(): array
    {
        app()->bind(PaymentGateway::class, FakePaymentGateway::class);

        $gateway = app(PaymentGateway::class);

        return [
            'resolved_class' => $gateway::class,
            'payment' => $gateway->charge(1000, 'cny', ['source' => 'container example']),
            'request_id' => app()->bound('interview.request_id') ? app('interview.request_id') : null,
        ];
    }

    /**
     * 面试点：Query Builder 适合复杂 SQL 组合，toSql/getBindings 可用于排查语句。
     *
     * @return array<string, mixed>
     */
    public function queryBuilderExamples(): array
    {
        $query = DB::table('users')
            ->select(['id', 'name', 'email'])
            ->where('email', 'like', '%@example.com')
            ->whereNull('deleted_at')
            ->orderByDesc('id');

        return [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'chunking_pattern' => 'DB::table("users")->orderBy("id")->chunkById(100, fn ($users) => ...);',
            'upsert_pattern' => 'DB::table("users")->upsert($rows, ["email"], ["name", "updated_at"]);',
        ];
    }

    /**
     * 面试点：Eloquent 关注模型关系、作用域、访问器、修改器、预加载和批量赋值保护。
     *
     * @return array<string, mixed>
     */
    public function eloquentExamples(): array
    {
        $query = Post::query()
            ->with(['author:id,name,email', 'comments.author:id,name'])
            ->withCount('comments')
            ->published()
            ->search('laravel')
            ->latest('published_at');

        return [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'fillable' => (new Post())->getFillable(),
            'n_plus_one_solution' => '使用 with/withCount/loadMissing 预加载关系，避免循环内反复查询。',
        ];
    }

    /**
     * 面试点：事务保证原子性，lockForUpdate 用于并发扣减库存、余额等场景。
     *
     * @return array<string, mixed>
     */
    public function transactionAndLockExamples(int $userId): array
    {
        return DB::transaction(function () use ($userId): array {
            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->first();

            return [
                'locked_user_id' => $user?->id,
                'retry_attempts' => 3,
                'note' => '真实扣库存或扣余额逻辑应放在同一个事务闭包内。',
            ];
        }, 3);
    }

    /**
     * 面试点：Cache::remember 封装了“读缓存，没有则计算并写入”的常用模式。
     *
     * @return array<string, mixed>
     */
    public function cacheExamples(): array
    {
        return Cache::remember('interview:dashboard_stats', now()->addMinutes(5), function (): array {
            return [
                'users' => DB::table('users')->count(),
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * 面试点：RateLimiter 可用于登录、短信验证码、接口限流等场景。
     *
     * @return array<string, mixed>
     */
    public function rateLimiterExamples(string $key): array
    {
        $allowed = RateLimiter::attempt(
            key: "interview:{$key}",
            maxAttempts: 5,
            callback: fn (): bool => true,
            decaySeconds: 60,
        );

        return [
            'allowed' => $allowed,
            'remaining' => RateLimiter::remaining("interview:{$key}", 5),
            'available_in_seconds' => RateLimiter::availableIn("interview:{$key}"),
        ];
    }

    /**
     * 面试点：Storage facade 抽象本地磁盘、S3 等存储驱动。
     *
     * @return array<string, mixed>
     */
    public function filesystemExamples(): array
    {
        Storage::disk('local')->put('interview-examples/hello.txt', 'hello laravel');

        return [
            'exists' => Storage::disk('local')->exists('interview-examples/hello.txt'),
            'content' => Storage::disk('local')->get('interview-examples/hello.txt'),
            'url_pattern' => 'Storage::disk("public")->url("avatars/1.png")',
        ];
    }

    /**
     * 面试点：事件用于解耦同步副作用，队列用于异步耗时任务。
     *
     * @return array<string, mixed>
     */
    public function queueAndEventExamples(): array
    {
        Event::dispatch(new OrderPaid(
            orderNumber: 'ORDER-DEMO',
            userId: 1,
            transactionId: 'TRANSACTION-DEMO',
            amountInCents: 9900,
        ));

        SendInterviewWelcomeMail::dispatch('demo@example.com', 'Demo User')->afterResponse();

        return [
            'event' => OrderPaid::class,
            'job' => SendInterviewWelcomeMail::class,
            'queue_commands' => [
                'php artisan queue:work',
                'php artisan queue:failed',
                'php artisan queue:retry all',
            ],
        ];
    }

    /**
     * 面试点：HTTP Client 支持超时、重试、fake，测试第三方接口时很常见。
     *
     * @return array<string, mixed>
     */
    public function httpClientExamples(): array
    {
        Http::fake([
            'api.example.test/*' => Http::response(['ok' => true, 'name' => 'Laravel'], 200),
        ]);

        $response = Http::acceptJson()
            ->timeout(3)
            ->retry(2, 100)
            ->get('https://api.example.test/frameworks/laravel');

        return [
            'successful' => $response->successful(),
            'json' => $response->json(),
        ];
    }

    /**
     * 面试点：Gate/Policy 负责授权，认证只回答“你是谁”，授权回答“你能否做这件事”。
     *
     * @return array<string, mixed>
     */
    public function authorizationExamples(): array
    {
        return [
            'can_view_examples' => Gate::allows('view-interview-examples'),
            'policy_pattern' => '$this->authorize("update", $post);',
            'blade_pattern' => '@can("update", $post) ... @endcan',
        ];
    }

    /**
     * 面试点：Helper 通常用于小型数据读取、字符串处理；复杂业务不要塞进 helper。
     *
     * @return array<string, mixed>
     */
    public function helperExamples(): array
    {
        $config = ['mail' => ['from' => ['address' => 'noreply@example.com']]];

        return [
            'arr_get' => Arr::get($config, 'mail.from.address'),
            'str_slug' => Str::slug('Laravel Interview Example'),
            'str_mask' => Str::mask('13800138000', '*', 3, 4),
            'config_app_name' => config('app.name'),
        ];
    }
}
