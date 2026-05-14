<?php

namespace Tests\Feature;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Messaging\Contracts\KafkaClient;
use App\Domains\Messaging\Contracts\KafkaMessageHandler;
use App\Domains\Messaging\KafkaConsumerService;
use App\Domains\Messaging\KafkaMessage;
use App\Domains\Messaging\KafkaMessageFactory;
use App\Domains\Messaging\KafkaRecord;
use App\Domains\Messaging\Models\ConsumedMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PhaseSevenKafkaMessagingTest extends TestCase
{
    use RefreshDatabase;

    private string $kafkaPath;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->kafkaPath = storage_path('framework/testing/kafka/'.Str::uuid());
        File::deleteDirectory($this->kafkaPath);
        config([
            'kafka.driver' => 'local',
            'kafka.local_path' => $this->kafkaPath,
            'kafka.retry.max_attempts' => 2,
        ]);

        $this->app->forgetInstance(KafkaClient::class);
        $this->app->forgetInstance(KafkaConsumerService::class);
    }

    public function test_kafka_produce_and_consume_writes_audit_log_once_with_idempotency(): void
    {
        $payload = json_encode([
            'import_task_id' => 501,
            'status' => 'completed',
            'total_rows' => 10,
            'success_rows' => 10,
            'failed_rows' => 0,
        ], JSON_THROW_ON_ERROR);

        $this->artisan('kafka:topics', ['--create' => true])
            ->assertSuccessful();

        $this->artisan('kafka:produce', [
            'event' => 'metric.import.completed',
            '--payload' => $payload,
            '--idempotency-key' => 'metric-import:501:completed:v1',
            '--trace-id' => 'trace-kafka-test',
        ])->assertSuccessful();

        $this->artisan('kafka:consume', [
            'consumer_group' => 'audit-log-consumer',
            '--max' => 1,
        ])->assertSuccessful();

        $this->assertDatabaseHas(AuditLog::class, [
            'action' => 'metric.import.completed',
            'resource_type' => 'import_task',
            'resource_id' => 501,
            'trace_id' => 'trace-kafka-test',
        ]);
        $this->assertDatabaseHas(ConsumedMessage::class, [
            'consumer_group' => 'audit-log-consumer',
            'idempotency_key' => 'metric-import:501:completed:v1',
            'status' => 'completed',
        ]);

        $auditCount = AuditLog::query()->count();

        $this->artisan('kafka:produce', [
            'event' => 'metric.import.completed',
            '--payload' => $payload,
            '--idempotency-key' => 'metric-import:501:completed:v1',
            '--trace-id' => 'trace-kafka-test',
        ])->assertSuccessful();
        $this->artisan('kafka:consume', [
            'consumer_group' => 'audit-log-consumer',
            '--max' => 1,
        ])->assertSuccessful();

        $this->assertSame($auditCount, AuditLog::query()->count());
    }

    public function test_metric_data_changed_event_refreshes_metric_cache(): void
    {
        Cache::put('metric:7:detail', ['name' => 'old'], 60);
        Cache::put('metrics:detail:7', ['name' => 'old'], 60);

        $payload = json_encode([
            'metric_id' => 7,
            'change_id' => 'change-7',
            'change_type' => 'updated',
            'changed_fields' => ['name'],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('kafka:topics', ['--create' => true])->assertSuccessful();
        $this->artisan('kafka:produce', [
            'event' => 'metric.data.changed',
            '--payload' => $payload,
            '--idempotency-key' => 'metric-data:7:change-7:v1',
        ])->assertSuccessful();
        $this->artisan('kafka:consume', [
            'consumer_group' => 'cache-refresh-consumer',
            '--max' => 1,
        ])->assertSuccessful();

        $this->assertFalse(Cache::has('metric:7:detail'));
        $this->assertFalse(Cache::has('metrics:detail:7'));
        $this->assertSame(1, Cache::get('kafka:cache_refreshes'));
    }

    public function test_failed_consume_attempt_is_dead_lettered_after_max_attempts(): void
    {
        $client = app(KafkaClient::class);
        $factory = app(KafkaMessageFactory::class);
        $handler = new class implements KafkaMessageHandler
        {
            #[\Override]
            public function supports(KafkaMessage $message, string $consumerGroup): bool
            {
                unset($message, $consumerGroup);

                return true;
            }

            #[\Override]
            public function handle(KafkaMessage $message, string $consumerGroup): void
            {
                unset($message, $consumerGroup);

                throw new RuntimeException('Simulated Kafka handler failure.');
            }
        };

        $service = new KafkaConsumerService($client, $factory, [$handler], 2);
        $message = $factory->make(
            eventType: 'metric.data.changed',
            payload: [
                'metric_id' => 9,
                'change_id' => 'change-failed',
                'change_type' => 'updated',
            ],
            idempotencyKey: 'metric-data:9:change-failed:v1',
        );
        $record = new KafkaRecord($message, $message->topic, 0, 0, $message->key);

        $this->assertSame('failed', $service->handleRecord($record, 'cache-refresh-consumer'));
        $this->assertSame('dead_lettered', $service->handleRecord($record, 'cache-refresh-consumer'));

        $this->assertDatabaseHas(ConsumedMessage::class, [
            'consumer_group' => 'cache-refresh-consumer',
            'idempotency_key' => 'metric-data:9:change-failed:v1',
            'status' => 'dead_lettered',
            'attempts' => 2,
        ]);

        $deadLetters = $client->consume('metrics.data.changed.dlq', 'assert-dlq', 1, 1000);
        $this->assertCount(1, $deadLetters);
        $this->assertSame('metrics.data.changed', $deadLetters[0]->message->payload['original_topic']);
    }
}
