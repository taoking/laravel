<?php

namespace App\Console\Commands;

use App\Domains\Messaging\Contracts\KafkaClient;
use Illuminate\Console\Command;

class KafkaTopicsCommand extends Command
{
    protected $signature = 'kafka:topics {--create : Create configured topics before listing them}';

    protected $description = 'List or create Kafka topics for the metrics learning platform.';

    public function handle(KafkaClient $client): int
    {
        $topics = $this->option('create')
            ? $client->createTopics()
            : $client->topics();

        foreach ($topics as $topic) {
            $this->line($topic);
        }

        $this->info('Kafka topics ready: '.count($topics));

        return self::SUCCESS;
    }
}
