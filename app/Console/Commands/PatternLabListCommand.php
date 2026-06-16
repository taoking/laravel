<?php

namespace App\Console\Commands;

use App\PatternLab\PatternRegistry;
use Illuminate\Console\Command;

class PatternLabListCommand extends Command
{
    protected $signature = 'pattern-lab:list';

    protected $description = 'List all PatternLab design pattern exercises.';

    public function handle(PatternRegistry $patterns): int
    {
        foreach ($patterns->all() as $pattern) {
            $this->line(sprintf(
                '[%s] %s %s - %s - %s',
                (string) $pattern['category'],
                (string) $pattern['name'],
                (string) $pattern['name_cn'],
                (string) $pattern['difficulty'],
                (string) $pattern['status'],
            ));
            $this->line('场景：'.(string) $pattern['scenario']);
            $this->line('常见：'.$this->joinList($pattern['common_usage'] ?? []));
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function joinList(mixed $value): string
    {
        if (! is_array($value)) {
            return '';
        }

        return implode('、', array_map('strval', $value));
    }
}
