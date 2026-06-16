<?php

namespace App\Console\Commands;

use App\PatternLab\PatternRegistry;
use Illuminate\Console\Command;

class PatternLabNextCommand extends Command
{
    protected $signature = 'pattern-lab:next';

    protected $description = 'Show the next recommended PatternLab exercise.';

    public function handle(PatternRegistry $patterns): int
    {
        $pattern = $patterns->next();

        if ($pattern === null) {
            $this->info('All PatternLab exercises are marked as done.');

            return self::SUCCESS;
        }

        $this->info('推荐下一个练习：');
        $this->line(sprintf(
            '%s / %s [%s] - %s',
            (string) $pattern['name'],
            (string) $pattern['name_cn'],
            (string) $pattern['category_cn'],
            (string) $pattern['difficulty'],
        ));
        $this->line('key：'.(string) $pattern['key']);
        $this->line('场景：'.(string) $pattern['scenario']);
        $exerciseContent = is_array($pattern['exercise_content'] ?? null)
            ? $pattern['exercise_content']
            : [];
        $this->line('具体功能：'.(string) ($exerciseContent['title'] ?? ''));
        $this->line('文档：'.(string) $pattern['doc_path']);
        $this->line('代码目录：'.(string) $pattern['exercise_path']);
        $this->line('测试文件：'.(string) $pattern['test_path']);

        return self::SUCCESS;
    }
}
