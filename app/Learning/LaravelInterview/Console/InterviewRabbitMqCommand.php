<?php

namespace App\Learning\LaravelInterview\Console;

use App\Learning\LaravelInterview\Support\RabbitMqInterviewExamples;
use Illuminate\Console\Command;

class InterviewRabbitMqCommand extends Command
{
    protected $signature = 'interview:rabbitmq
        {--key=demo : RabbitMQ topology suffix}
        {--json : Output the complete payload as JSON}';

    protected $description = '演示 RabbitMQ exchange、queue、binding、publish、consume/ack 和死信拓扑。';

    public function handle(RabbitMqInterviewExamples $examples): int
    {
        $payload = $examples->run((string) $this->option('key'));

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('RabbitMQ interview examples');

        $this->table(['Topic', 'Result'], [
            ['Exchange', $payload['topology']['exchange']],
            ['Queue', $payload['topology']['queue']],
            ['Routing Key', $payload['topology']['routing_key']],
            ['Dead Letter Exchange', $payload['topology']['dead_letter_exchange']],
            ['Dead Letter Queue', $payload['topology']['dead_letter_queue']],
            ['Published', $payload['publish']['routed'] ? 'routed' : 'not routed'],
            ['Consumed', 'messages='.$payload['consume']['message_count'].' ackmode='.$payload['consume']['ackmode']],
            ['Payload', json_encode($payload['consume']['payload'], JSON_UNESCAPED_UNICODE)],
        ]);

        foreach ($payload['interview_points'] as $point) {
            $this->line('- '.$point);
        }

        return self::SUCCESS;
    }
}
