<?php

namespace App\Learning\LaravelInterview\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InterviewReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $topic)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Laravel 面试复习提醒')
            ->line("请复习：{$this->topic}")
            ->action('Open App', url('/'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'topic' => $this->topic,
            'created_at' => now()->toISOString(),
        ];
    }
}
