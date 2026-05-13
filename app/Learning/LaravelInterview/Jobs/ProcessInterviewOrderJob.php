<?php

namespace App\Learning\LaravelInterview\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessInterviewOrderJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly string $orderNo,
    ) {
        $this->onQueue('interview-orders');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))->expireAfter(300),
        ];
    }

    public function uniqueId(): string
    {
        return 'interview-order:'.$this->orderNo;
    }

    public function handle(): void
    {
        Log::info('processing interview order job', [
            'order_no' => $this->orderNo,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('interview order job failed', [
            'order_no' => $this->orderNo,
            'message' => $exception->getMessage(),
        ]);
    }
}
