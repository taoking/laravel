<?php

namespace App\Learning\LaravelInterview\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InterviewWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $name)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Laravel 面试样例')
            ->view('emails.interview-welcome', [
                'name' => $this->name,
            ]);
    }
}
