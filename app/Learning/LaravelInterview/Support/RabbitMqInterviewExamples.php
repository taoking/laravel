<?php

namespace App\Learning\LaravelInterview\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class RabbitMqInterviewExamples
{
    /**
     * @return array<string, mixed>
     */
    public function run(string $suffix = 'demo'): array
    {
        $safeSuffix = Str::slug($suffix) ?: 'demo';
        $vhost = rawurlencode((string) config('interview_examples.infrastructure.rabbitmq.vhost'));
        $exchange = "interview.{$safeSuffix}.topic";
        $queue = "interview.{$safeSuffix}.queue";
        $deadLetterExchange = "interview.{$safeSuffix}.dlx";
        $deadLetterQueue = "interview.{$safeSuffix}.dead";
        $routingKey = 'order.paid';

        $http = $this->http();

        $this->deleteIfExists($http, "queues/{$vhost}/{$queue}");
        $this->deleteIfExists($http, "queues/{$vhost}/{$deadLetterQueue}");

        $this->ensureOk($http->put("exchanges/{$vhost}/{$exchange}", [
            'type' => 'topic',
            'durable' => false,
            'auto_delete' => true,
        ]), 'declare topic exchange');

        $this->ensureOk($http->put("exchanges/{$vhost}/{$deadLetterExchange}", [
            'type' => 'topic',
            'durable' => false,
            'auto_delete' => true,
        ]), 'declare dead letter exchange');

        $this->ensureOk($http->put("queues/{$vhost}/{$queue}", [
            'durable' => false,
            'auto_delete' => true,
            'arguments' => [
                'x-dead-letter-exchange' => $deadLetterExchange,
            ],
        ]), 'declare queue');

        $this->ensureOk($http->put("queues/{$vhost}/{$deadLetterQueue}", [
            'durable' => false,
            'auto_delete' => true,
        ]), 'declare dead letter queue');

        $this->ensureOk($http->post("bindings/{$vhost}/e/{$exchange}/q/{$queue}", [
            'routing_key' => $routingKey,
        ]), 'bind queue');

        $this->ensureOk($http->post("bindings/{$vhost}/e/{$deadLetterExchange}/q/{$deadLetterQueue}", [
            'routing_key' => '#',
        ]), 'bind dead letter queue');

        $payload = json_encode([
            'order_no' => 'ORDER-DEMO',
            'event' => 'paid',
            'sent_at' => now()->toISOString(),
        ], JSON_UNESCAPED_UNICODE);

        $publish = $this->ensureOk($http->post("exchanges/{$vhost}/{$exchange}/publish", [
            'properties' => [
                'content_type' => 'application/json',
                'delivery_mode' => 1,
            ],
            'routing_key' => $routingKey,
            'payload' => $payload,
            'payload_encoding' => 'string',
        ]), 'publish message')->json();

        $messages = $this->ensureOk($http->post("queues/{$vhost}/{$queue}/get", [
            'count' => 1,
            'ackmode' => 'ack_requeue_false',
            'encoding' => 'auto',
            'truncate' => 50000,
        ]), 'consume message')->json();

        $message = $messages[0] ?? null;

        return [
            'topology' => [
                'exchange' => $exchange,
                'queue' => $queue,
                'routing_key' => $routingKey,
                'dead_letter_exchange' => $deadLetterExchange,
                'dead_letter_queue' => $deadLetterQueue,
            ],
            'publish' => [
                'routed' => (bool) ($publish['routed'] ?? false),
                'payload' => json_decode($payload, true),
            ],
            'consume' => [
                'ackmode' => 'ack_requeue_false',
                'message_count' => count($messages),
                'payload' => $message ? json_decode((string) $message['payload'], true) : null,
            ],
            'retry_and_dead_letter_pattern' => [
                'retry' => '失败消息进入 retry queue，设置 TTL 后通过 dead-letter-exchange 回到主 exchange。',
                'dead_letter' => '超过最大重试次数后投递到 dead letter queue，等待人工排查或补偿任务处理。',
            ],
            'interview_points' => [
                'Exchange 负责路由，Queue 负责存储，Binding 连接两者。',
                'ACK 决定消息是否可以从队列删除，消费者必须保证业务幂等。',
                '消息可靠性需要同时考虑 publisher confirm、持久化、ACK、重试和死信。',
                'RabbitMQ 适合业务事件、任务削峰和路由灵活的异步场景。',
                '消息重复消费是常态，订单、支付、库存类任务必须有唯一键或状态机保护。',
            ],
        ];
    }

    private function http(): PendingRequest
    {
        $host = (string) config('interview_examples.infrastructure.rabbitmq.host');
        $port = (int) config('interview_examples.infrastructure.rabbitmq.management_port');
        $user = (string) config('interview_examples.infrastructure.rabbitmq.user');
        $password = (string) config('interview_examples.infrastructure.rabbitmq.password');

        return Http::baseUrl("http://{$host}:{$port}/api")
            ->withBasicAuth($user, $password)
            ->acceptJson()
            ->timeout(5);
    }

    private function deleteIfExists(PendingRequest $http, string $uri): void
    {
        $response = $http->delete($uri);

        if ($response->successful() || $response->status() === 404) {
            return;
        }

        $this->ensureOk($response, "delete {$uri}");
    }

    private function ensureOk(Response $response, string $operation): Response
    {
        if ($response->successful()) {
            return $response;
        }

        throw new RuntimeException("RabbitMQ {$operation} failed: HTTP {$response->status()} {$response->body()}");
    }
}
