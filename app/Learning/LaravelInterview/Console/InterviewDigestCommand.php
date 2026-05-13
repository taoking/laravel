<?php

namespace App\Learning\LaravelInterview\Console;

use App\Learning\LaravelInterview\Jobs\SendInterviewWelcomeMail;
use App\Learning\LaravelInterview\Support\LaravelComponentCheatsheet;
use Illuminate\Console\Command;

class InterviewDigestCommand extends Command
{
    protected $signature = 'interview:digest
        {--user= : 可选用户 ID}
        {--queue : 是否派发一个队列任务样例}';

    protected $description = '输出 Laravel 常见面试组件的样例摘要。';

    public function handle(LaravelComponentCheatsheet $cheatsheet): int
    {
        $this->info('Laravel interview examples');

        $this->table(
            ['Topic', 'Example'],
            [
                ['Collection', json_encode($cheatsheet->collectionExamples(), JSON_UNESCAPED_UNICODE)],
                ['Container', 'bind / singleton / scoped / interface injection'],
                ['Eloquent', 'relationship / scope / cast / accessor / eager loading'],
                ['Queue', 'ShouldQueue / tries / backoff / failed'],
            ],
        );

        if ($this->option('queue')) {
            SendInterviewWelcomeMail::dispatch('demo@example.com', 'Demo User');
            $this->components->info('Queued SendInterviewWelcomeMail job.');
        }

        return self::SUCCESS;
    }
}
