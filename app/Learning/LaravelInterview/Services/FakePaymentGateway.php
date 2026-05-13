<?php

namespace App\Learning\LaravelInterview\Services;

use App\Learning\LaravelInterview\Contracts\PaymentGateway;
use Illuminate\Support\Str;

class FakePaymentGateway implements PaymentGateway
{
    public function charge(int $amountInCents, string $currency, array $metadata = []): array
    {
        return [
            'transaction_id' => (string) Str::uuid(),
            'amount_in_cents' => $amountInCents,
            'currency' => strtoupper($currency),
            'status' => 'paid',
            'metadata' => $metadata,
        ];
    }

    public function refund(string $transactionId, int $amountInCents): array
    {
        return [
            'refund_id' => (string) Str::uuid(),
            'transaction_id' => $transactionId,
            'amount_in_cents' => $amountInCents,
            'status' => 'refunded',
        ];
    }
}
