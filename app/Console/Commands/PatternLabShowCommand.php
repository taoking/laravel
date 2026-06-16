<?php

namespace App\Console\Commands;

use App\PatternLab\PatternRegistry;
use Illuminate\Console\Command;

class PatternLabShowCommand extends Command
{
    protected $signature = 'pattern-lab:show {pattern : Pattern key, for example strategy or factory-method}';

    protected $description = 'Show one PatternLab design pattern exercise.';

    public function handle(PatternRegistry $patterns): int
    {
        $pattern = $patterns->find((string) $this->argument('pattern'));

        if ($pattern === null) {
            $this->error('Pattern not found. Use php artisan pattern-lab:list to see available keys.');

            return self::FAILURE;
        }

        $this->writePattern($pattern);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $pattern
     */
    private function writePattern(array $pattern): void
    {
        $this->line('模式：'.(string) $pattern['name'].' / '.(string) $pattern['name_cn']);
        $this->line('分类：'.(string) $pattern['category_name'].' / '.(string) $pattern['category_cn']);
        $this->line('难度：'.(string) $pattern['difficulty']);
        $this->line('状态：'.(string) $pattern['status']);
        $this->newLine();
        $this->line('一句话定义：');
        $this->line((string) $pattern['summary']);
        $this->newLine();
        $this->line('使用场景：');
        $this->line((string) $pattern['scenario']);
        $this->newLine();
        $this->line('练习目标：');

        foreach ($this->stringList($pattern['exercise_goals'] ?? []) as $goal) {
            $this->line('- '.$goal);
        }

        $this->newLine();
        $this->line('练习内容：');
        $exerciseContent = is_array($pattern['exercise_content'] ?? null)
            ? $pattern['exercise_content']
            : [];
        $this->line('具体功能：'.(string) ($exerciseContent['title'] ?? ''));
        $this->line((string) ($exerciseContent['description'] ?? ''));

        foreach ($this->stringList($exerciseContent['requirements'] ?? []) as $requirement) {
            $this->line('- '.$requirement);
        }

        $this->newLine();
        $this->line('常见使用地方：');

        foreach ($this->stringList($pattern['common_usage'] ?? []) as $usage) {
            $this->line('- '.$usage);
        }

        $this->newLine();
        $this->line('文档：');
        $this->line((string) $pattern['doc_path']);
        $this->newLine();
        $this->line('代码目录：');
        $this->line((string) $pattern['exercise_path']);
        $this->newLine();
        $this->line('测试文件：');
        $this->line((string) $pattern['test_path']);
        $this->newLine();
        $this->line('练习要求：');
        $this->line('请阅读文档并自己实现，不要依赖自动生成答案。');
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map('strval', $value));
    }
}
