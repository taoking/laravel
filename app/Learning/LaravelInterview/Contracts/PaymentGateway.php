<?php

namespace App\Learning\LaravelInterview\Contracts;

interface PaymentGateway
{
    /**
     * 面试点：依赖接口而不是具体类，便于测试替身、支付渠道切换和服务容器绑定。
     *
     * @return array{transaction_id: string, amount_in_cents: int, currency: string, status: string, metadata: array<string, mixed>}
     */
    public function charge(int $amountInCents, string $currency, array $metadata = []): array;

    /**
     * @return array{refund_id: string, transaction_id: string, amount_in_cents: int, status: string}
     */
    public function refund(string $transactionId, int $amountInCents): array;
}
