<?php

namespace App\Domains\Messaging\Clients;

use App\Domains\Messaging\Contracts\KafkaClient;
use App\Domains\Messaging\KafkaMessage;
use App\Domains\Messaging\KafkaRecord;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class LocalKafkaClient implements KafkaClient
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly string $basePath,
    ) {}

    #[\Override]
    public function publish(KafkaMessage $message): void
    {
        $this->ensureDirectory($this->topicsPath());

        $line = json_encode([
            'key' => $message->key,
            'message' => $message->toArray(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($line === false) {
            throw new RuntimeException('Unable to encode Kafka message.');
        }

        $this->files->append($this->topicPath($message->topic), $line.PHP_EOL);
    }

    #[\Override]
    public function consume(string $topic, string $consumerGroup, int $maxMessages, int $timeoutMs): array
    {
        unset($timeoutMs);

        $topicPath = $this->topicPath($topic);
        if (! $this->files->exists($topicPath)) {
            return [];
        }

        $lines = file($topicPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $offset = $this->readOffset($topic, $consumerGroup);
        $records = [];

        foreach (array_slice($lines, $offset, $maxMessages, true) as $index => $line) {
            $decoded = json_decode((string) $line, true);

            if (! is_array($decoded) || ! is_array($decoded['message'] ?? null)) {
                continue;
            }

            $key = (string) ($decoded['key'] ?? '');
            $records[] = new KafkaRecord(
                KafkaMessage::fromArray($decoded['message'], $topic, $key),
                $topic,
                0,
                (int) $index,
                $key,
            );
        }

        $this->writeOffset($topic, $consumerGroup, $offset + count($records));

        return $records;
    }

    #[\Override]
    public function topics(): array
    {
        if (! $this->files->isDirectory($this->topicsPath())) {
            return [];
        }

        return collect($this->files->files($this->topicsPath()))
            ->map(fn ($file): string => str_replace('.jsonl', '', $file->getFilename()))
            ->sort()
            ->values()
            ->all();
    }

    #[\Override]
    public function createTopics(): array
    {
        $this->ensureDirectory($this->topicsPath());

        $topics = $this->configuredTopics();
        foreach ($topics as $topic) {
            $path = $this->topicPath($topic);
            if (! $this->files->exists($path)) {
                $this->files->put($path, '');
            }
        }

        return $topics;
    }

    #[\Override]
    public function lag(string $topic, string $consumerGroup): int
    {
        $topicPath = $this->topicPath($topic);
        if (! $this->files->exists($topicPath)) {
            return 0;
        }

        $lines = file($topicPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return 0;
        }

        return max(0, count($lines) - $this->readOffset($topic, $consumerGroup));
    }

    private function topicsPath(): string
    {
        return $this->basePath.'/topics';
    }

    private function topicPath(string $topic): string
    {
        return $this->topicsPath().'/'.$topic.'.jsonl';
    }

    private function offsetPath(string $topic, string $consumerGroup): string
    {
        return $this->basePath.'/offsets/'.$consumerGroup.'/'.$topic.'.offset';
    }

    private function readOffset(string $topic, string $consumerGroup): int
    {
        $path = $this->offsetPath($topic, $consumerGroup);

        if (! $this->files->exists($path)) {
            return 0;
        }

        return max(0, (int) $this->files->get($path));
    }

    private function writeOffset(string $topic, string $consumerGroup, int $offset): void
    {
        $path = $this->offsetPath($topic, $consumerGroup);
        $this->ensureDirectory(dirname($path));
        $this->files->put($path, (string) $offset);
    }

    /**
     * @return list<string>
     */
    private function configuredTopics(): array
    {
        $topics = [];

        foreach (config('kafka.topics', []) as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $topic = (string) ($definition['topic'] ?? '');
            $deadLetterTopic = (string) ($definition['dead_letter_topic'] ?? '');

            if ($topic !== '') {
                $topics[] = $topic;
            }

            if ($deadLetterTopic !== '') {
                $topics[] = $deadLetterTopic;
            }
        }

        return array_values(array_unique($topics));
    }

    private function ensureDirectory(string $path): void
    {
        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }
}
