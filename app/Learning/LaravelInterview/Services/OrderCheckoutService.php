<?php

namespace App\Learning\LaravelInterview\Services;

use App\Learning\LaravelInterview\Contracts\PaymentGateway;
use App\Learning\LaravelInterview\Events\OrderPaid;
use App\Learning\LaravelInterview\Jobs\SendInterviewWelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderCheckoutService
{
    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    /**
     * 面试点：业务服务层负责组织事务、缓存、事件和队列，Controller 只做 HTTP 编排。
     *
     * @param  array{items: array<int, array<string, mixed>>, total_cents: int}  $cart
     * @return array{order_number: string, payment: array<string, mixed>}
     */
    public function checkout(User $user, array $cart): array
    {
        return DB::transaction(function () use ($user, $cart): array {
            $orderNumber = 'ORDER-'.now()->format('YmdHis').'-'.$user->getKey();

            $payment = $this->gateway->charge(
                amountInCents: (int) $cart['total_cents'],
                currency: 'CNY',
                metadata: [
                    'order_number' => $orderNumber,
                    'user_id' => $user->getKey(),
                ],
            );

            Cache::put(
                key: "orders:{$orderNumber}:last_payment",
                value: $payment,
                ttl: now()->addMinutes(30),
            );

            OrderPaid::dispatch(
                orderNumber: $orderNumber,
                userId: (int) $user->getKey(),
                transactionId: (string) $payment['transaction_id'],
                amountInCents: (int) $cart['total_cents'],
            );

            SendInterviewWelcomeMail::dispatch($user->email, $user->name)->onQueue('emails');

            Log::info('interview example order paid', [
                'order_number' => $orderNumber,
                'user_id' => $user->getKey(),
            ]);

            return [
                'order_number' => $orderNumber,
                'payment' => $payment,
            ];
        }, attempts: 3);
    }
}
