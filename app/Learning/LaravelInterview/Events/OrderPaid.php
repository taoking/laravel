<?php

namespace App\Learning\LaravelInterview\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $orderNumber,
        public readonly int $userId,
        public readonly string $transactionId,
        public readonly int $amountInCents,
    ) {
    }
}
