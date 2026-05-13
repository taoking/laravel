<?php

namespace App\Learning\LaravelInterview\Jobs;

use App\Learning\LaravelInterview\Mail\InterviewWelcomeMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInterviewWelcomeMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly string $email,
        public readonly string $name,
    ) {
    }

    public function handle(): void
    {
        Mail::to($this->email)->send(new InterviewWelcomeMail($this->name));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('interview welcome mail failed', [
            'email' => $this->email,
            'message' => $exception->getMessage(),
        ]);
    }
}
