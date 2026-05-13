<?php

namespace App\Learning\LaravelInterview\Listeners;

use App\Learning\LaravelInterview\Events\OrderPaid;
use Illuminate\Support\Facades\Log;

class WriteOrderPaidAuditLog
{
    public function handle(OrderPaid $event): void
    {
        Log::info('interview example order paid event handled', [
            'order_number' => $event->orderNumber,
            'user_id' => $event->userId,
            'transaction_id' => $event->transactionId,
            'amount_in_cents' => $event->amountInCents,
        ]);
    }
}
