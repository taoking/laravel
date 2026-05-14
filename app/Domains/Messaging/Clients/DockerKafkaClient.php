<?php

namespace App\Domains\Messaging\Clients;

use App\Domains\Messaging\Contracts\KafkaClient;
use App\Domains\Messaging\KafkaMessage;
use App\Domains\Messaging\KafkaRecord;
use RuntimeException;
use Symfony\Component\Process\Process;

class DockerKafkaClient implements KafkaClient
{
    /**
     * @param  list<string>  $composeCommand
     */
    public function __construct(
        private readonly array $composeCommand,
        private readonly string $service,
        private readonly string $brokers,
        private readonly int $timeout,
    ) {}

    #[\Override]
    public function publish(KafkaMessage $message): void
    {
        $process = $this->process($this->kafkaBin('kafka-console-producer.sh')
            .' --bootstrap-server '.escapeshellarg($this->brokers)
            .' --topic '.escapeshellarg($message->topic)
            .' --property parse.key=true --property key.separator='.escapeshellarg("\t"));
        $process->setInput($message->key."\t".$message->toJson().PHP_EOL);
        $process->run();

        $this->ensureSuccessful($process);
    }

    #[\Override]
    public function consume(string $topic, string $consumerGroup, int $maxMessages, int $timeoutMs): array
    {
        $process = $this->process($this->kafkaBin('kafka-console-consumer.sh')
            .' --bootstrap-server '.escapeshellarg($this->brokers)
            .' --topic '.escapeshellarg($topic)
            .' --group '.escapeshellarg($consumerGroup)
            .' --from-beginning'
            .' --max-messages '.max(1, $maxMessages)
            .' --timeout-ms '.max(100, $timeoutMs)
            .' --property print.key=true --property key.separator='.escapeshellarg("\t"));
        $process->run();

        $output = trim($process->getOutput());
        if ($output === '') {
            return [];
        }

        $records = [];
        foreach (explode(PHP_EOL, $output) as $offset => $line) {
            [$key, $json] = str_contains($line, "\t")
                ? explode("\t", $line, 2)
                : ['', $line];

            $decoded = json_decode($json, true);
            if (! is_array($decoded)) {
                continue;
            }

            $records[] = new KafkaRecord(
                KafkaMessage::fromArray($decoded, $topic, $key),
                $topic,
                0,
                (int) $offset,
                $key,
            );
        }

        return $records;
    }

    #[\Override]
    public function topics(): array
    {
        $process = $this->process($this->kafkaBin('kafka-topics.sh')
            .' --bootstrap-server '.escapeshellarg($this->brokers)
            .' --list');
        $process->run();
        $this->ensureSuccessful($process);

        $topics = array_filter(array_map('trim', explode(PHP_EOL, $process->getOutput())));
        sort($topics);

        return array_values($topics);
    }

    #[\Override]
    public function createTopics(): array
    {
        foreach ($this->configuredTopics() as $definition) {
            $topic = (string) ($definition['topic'] ?? '');
            if ($topic === '') {
                continue;
            }

            $partitions = max(1, (int) ($definition['partitions'] ?? 1));
            $process = $this->process($this->kafkaBin('kafka-topics.sh')
                .' --bootstrap-server '.escapeshellarg($this->brokers)
                .' --create --if-not-exists'
                .' --topic '.escapeshellarg($topic)
                .' --partitions '.$partitions
                .' --replication-factor 1');
            $process->run();
            $this->ensureSuccessful($process);
        }

        return $this->topics();
    }

    #[\Override]
    public function lag(string $topic, string $consumerGroup): int
    {
        $process = $this->process($this->kafkaBin('kafka-consumer-groups.sh')
            .' --bootstrap-server '.escapeshellarg($this->brokers)
            .' --describe --group '.escapeshellarg($consumerGroup));
        $process->run();

        if (! $process->isSuccessful()) {
            return 0;
        }

        $lag = 0;
        foreach (explode(PHP_EOL, $process->getOutput()) as $line) {
            if (! str_contains($line, $topic)) {
                continue;
            }

            $columns = preg_split('/\s+/', trim($line));
            if (is_array($columns) && count($columns) >= 6) {
                $lag += max(0, (int) $columns[5]);
            }
        }

        return $lag;
    }

    private function process(string $script): Process
    {
        $command = [
            ...$this->composeCommand,
            'exec',
            '-T',
            $this->service,
            'bash',
            '-lc',
            $script,
        ];

        return new Process($command, base_path(), null, null, $this->timeout);
    }

    private function kafkaBin(string $binary): string
    {
        return '$(command -v '.$binary.' || echo /opt/kafka/bin/'.$binary.')';
    }

    private function ensureSuccessful(Process $process): void
    {
        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function configuredTopics(): array
    {
        $topics = [];
        foreach (config('kafka.topics', []) as $definition) {
            if (is_array($definition)) {
                $topics[] = $definition;
                $topics[] = [
                    'topic' => (string) ($definition['dead_letter_topic'] ?? ''),
                    'partitions' => (int) ($definition['partitions'] ?? 1),
                ];
            }
        }

        return $topics;
    }
}
