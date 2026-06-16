<?php

namespace App\PhpLab;

final class PhpLabRegistry
{
    /**
     * @return array<int, array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }>
     */
    public function all(): array
    {
        return [
            $this->basicTypes(),
            $this->strings(),
            $this->arrays(),
            $this->functionsAndClosures(),
            $this->controlFlow(),
            $this->objectOriented(),
            $this->exceptions(),
            $this->generators(),
        ];
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }>
     */
    public function keyed(): array
    {
        $topics = [];

        foreach ($this->all() as $topic) {
            $topics[$topic['key']] = $topic;
        }

        return $topics;
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }|null
     */
    public function find(string $key): ?array
    {
        $key = str_replace('_', '-', strtolower(trim($key)));

        return $this->keyed()[$key] ?? null;
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function basicTypes(): array
    {
        $name = 'Laravel';
        $count = '12';
        $price = 99.5;
        $isPublished = true;
        $payload = null;

        return [
            'key' => 'basic-types',
            'title' => '变量与基础类型',
            'level' => '入门',
            'summary' => '练习变量命名、标量类型、类型转换、空值判断和严格比较。',
            'concepts' => [
                '变量以 $ 开头，命名应该表达业务含义。',
                'PHP 常见标量类型包括 int、float、string、bool。',
                '使用 === 避免隐式类型转换带来的判断偏差。',
                'null 合并运算符 ?? 适合给可空数据设置默认值。',
            ],
            'code' => <<<'PHP'
$name = 'Laravel';
$count = '12';
$price = 99.5;
$isPublished = true;
$payload = null;

$total = (int) $count * $price;
$status = $isPublished === true ? 'published' : 'draft';
$displayName = $payload['name'] ?? $name;
PHP,
            'output' => [
                'total='.number_format((int) $count * $price, 2, '.', ''),
                'status='.($isPublished === true ? 'published' : 'draft'),
                'display_name='.($payload['name'] ?? $name),
            ],
            'practice' => [
                '把字符串 "15" 转成整数后参与金额计算。',
                '分别用 == 和 === 比较 0、false、"0"，观察结果差异。',
                '为一个可能为 null 的数组字段设置默认值。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function strings(): array
    {
        $title = '  PHP Basics Lab  ';
        $slug = str_replace(' ', '-', strtolower(trim($title)));
        $contains = str_contains($slug, 'php');

        return [
            'key' => 'strings',
            'title' => '字符串处理',
            'level' => '入门',
            'summary' => '练习字符串清理、大小写转换、查找、替换和格式化输出。',
            'concepts' => [
                'trim() 常用于清理用户输入两侧空白。',
                'str_contains()、str_starts_with()、str_ends_with() 可读性优于手写 strpos 判断。',
                'str_replace() 适合简单替换，复杂规则再考虑正则。',
                'sprintf() 适合生成固定格式的文本。',
            ],
            'code' => <<<'PHP'
$title = '  PHP Basics Lab  ';
$slug = str_replace(' ', '-', strtolower(trim($title)));
$contains = str_contains($slug, 'php');
$message = sprintf('topic=%s, contains_php=%s', $slug, $contains ? 'yes' : 'no');
PHP,
            'output' => [
                'slug='.$slug,
                'contains_php='.($contains ? 'yes' : 'no'),
                sprintf('topic=%s, contains_php=%s', $slug, $contains ? 'yes' : 'no'),
            ],
            'practice' => [
                '把用户输入的标题转换成小写短横线 slug。',
                '判断一个邮箱是否以指定公司域名结尾。',
                '使用 sprintf() 生成订单编号展示文案。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function arrays(): array
    {
        $scores = [
            ['name' => 'array', 'score' => 70],
            ['name' => 'function', 'score' => 88],
            ['name' => 'object', 'score' => 92],
        ];

        $passed = array_filter($scores, fn (array $row): bool => $row['score'] >= 80);
        $names = array_map(fn (array $row): string => $row['name'], $passed);
        $total = array_reduce($scores, fn (int $carry, array $row): int => $carry + $row['score'], 0);

        return [
            'key' => 'arrays',
            'title' => '数组与常用数组函数',
            'level' => '入门',
            'summary' => '练习索引数组、关联数组、过滤、映射、聚合和解构。',
            'concepts' => [
                'PHP 数组既可以当 list，也可以当 map；实际项目要明确数据形状。',
                'array_filter() 负责筛选，array_map() 负责转换，array_reduce() 负责聚合。',
                '读取关联数组字段前要考虑默认值或数据校验。',
                'Laravel Collection 与数组函数思想类似，但链式表达更强。',
            ],
            'code' => <<<'PHP'
$scores = [
    ['name' => 'array', 'score' => 70],
    ['name' => 'function', 'score' => 88],
    ['name' => 'object', 'score' => 92],
];

$passed = array_filter($scores, fn (array $row): bool => $row['score'] >= 80);
$names = array_map(fn (array $row): string => $row['name'], $passed);
$total = array_reduce($scores, fn (int $carry, array $row): int => $carry + $row['score'], 0);
PHP,
            'output' => [
                'passed='.implode(', ', $names),
                'total='.$total,
                'average='.number_format($total / count($scores), 2, '.', ''),
            ],
            'practice' => [
                '从商品数组中过滤出库存大于 0 的商品。',
                '把用户数组转换成只包含 id 和 name 的数组。',
                '统计订单金额总和并计算平均值。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function functionsAndClosures(): array
    {
        $subtotal = $this->subtotal([40, 60, 100], discount: 20);
        $multiplier = 3;
        $triple = fn (int $value): int => $value * $multiplier;

        return [
            'key' => 'functions',
            'title' => '函数、参数与闭包',
            'level' => '基础',
            'summary' => '练习类型声明、默认参数、命名参数、可变参数、闭包和箭头函数。',
            'concepts' => [
                '函数参数和返回值尽量声明类型，便于静态分析和团队协作。',
                '命名参数能提升多参数调用的可读性。',
                '...$values 可接收可变数量参数，也可用于数组展开。',
                '箭头函数自动按值捕获外部变量，适合短回调。',
            ],
            'code' => <<<'PHP'
function subtotal(array $prices, int $discount = 0): int
{
    return array_sum($prices) - $discount;
}

$subtotal = subtotal([40, 60, 100], discount: 20);
$multiplier = 3;
$triple = fn (int $value): int => $value * $multiplier;
PHP,
            'output' => [
                'subtotal='.$subtotal,
                'triple_7='.$triple(7),
            ],
            'practice' => [
                '写一个函数接收多个分数并返回最高分。',
                '用命名参数调用一个包含默认值的函数。',
                '用闭包完成价格数组的折扣计算。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function controlFlow(): array
    {
        $role = 'editor';
        $permission = match ($role) {
            'admin' => 'all',
            'editor' => 'write',
            'viewer' => 'read',
            default => 'none',
        };

        $steps = [];

        for ($index = 1; $index <= 3; $index++) {
            $steps[] = 'step-'.$index;
        }

        return [
            'key' => 'control-flow',
            'title' => '流程控制',
            'level' => '基础',
            'summary' => '练习 if、match、for、foreach、break、continue 和早返回。',
            'concepts' => [
                'match 使用严格比较，并且必须覆盖所有可能分支或提供 default。',
                'foreach 更适合遍历数组，for 更适合固定次数循环。',
                '早返回可以减少深层嵌套，让业务条件更清晰。',
                'break 和 continue 要谨慎使用，避免循环逻辑难读。',
            ],
            'code' => <<<'PHP'
$role = 'editor';

$permission = match ($role) {
    'admin' => 'all',
    'editor' => 'write',
    'viewer' => 'read',
    default => 'none',
};

for ($index = 1; $index <= 3; $index++) {
    $steps[] = 'step-'.$index;
}
PHP,
            'output' => [
                'permission='.$permission,
                'steps='.implode(' > ', $steps),
            ],
            'practice' => [
                '用 match 把订单状态转换成中文展示文案。',
                '用 foreach 汇总一个班级每个学生的分数。',
                '重写一个多层 if，让非法输入提前返回。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function objectOriented(): array
    {
        $lesson = new PhpLabLesson('oop', '面向对象基础');
        $lesson->publish();

        return [
            'key' => 'oop',
            'title' => '类、对象、接口与枚举',
            'level' => '进阶',
            'summary' => '练习构造函数、属性、方法、接口、枚举、readonly 和对象状态变化。',
            'concepts' => [
                '类把数据和行为组织在一起，适合表达有生命周期的业务对象。',
                '接口定义能力边界，调用方依赖接口能降低耦合。',
                'enum 适合替代散落的状态字符串。',
                'readonly 属性适合表达创建后不应变化的数据。',
            ],
            'code' => <<<'PHP'
enum LessonStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

final class Lesson
{
    public LessonStatus $status = LessonStatus::Draft;

    public function __construct(
        public readonly string $key,
        public readonly string $title,
    ) {}

    public function publish(): void
    {
        $this->status = LessonStatus::Published;
    }
}
PHP,
            'output' => [
                'lesson='.$lesson->key.' / '.$lesson->title,
                'status='.$lesson->status->value,
            ],
            'practice' => [
                '定义一个 UserProfile 类，包含姓名、邮箱和激活状态。',
                '用 enum 表达订单状态，并为每个状态提供中文 label。',
                '定义一个接口，让不同通知渠道实现同一个 send 方法。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function exceptions(): array
    {
        try {
            $result = $this->divide(100, 0);
        } catch (\InvalidArgumentException $exception) {
            $result = 'error: '.$exception->getMessage();
        } finally {
            $closed = true;
        }

        return [
            'key' => 'exceptions',
            'title' => '异常处理',
            'level' => '基础',
            'summary' => '练习 throw、try、catch、finally 和异常边界设计。',
            'concepts' => [
                '异常适合表达无法继续当前流程的错误。',
                'catch 应该捕获自己能处理或能补充上下文的异常。',
                'finally 无论是否抛异常都会执行，常用于释放资源。',
                '业务层不要吞掉异常后返回含糊结果。',
            ],
            'code' => <<<'PHP'
function divide(int $left, int $right): float
{
    if ($right === 0) {
        throw new InvalidArgumentException('除数不能为 0');
    }

    return $left / $right;
}

try {
    $result = divide(100, 0);
} catch (InvalidArgumentException $exception) {
    $result = 'error: '.$exception->getMessage();
} finally {
    $closed = true;
}
PHP,
            'output' => [
                $result,
                'finally_closed='.($closed ? 'yes' : 'no'),
            ],
            'practice' => [
                '给用户注册逻辑增加邮箱格式异常。',
                '捕获一个业务异常并转换成页面错误提示。',
                '在 finally 中记录一次资源释放状态。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function generators(): array
    {
        $rows = [];

        foreach ($this->metricRows(3) as $row) {
            $rows[] = $row['code'].':'.$row['value'];
        }

        return [
            'key' => 'generators',
            'title' => '生成器与惰性遍历',
            'level' => '进阶',
            'summary' => '练习 yield、逐行处理和低内存数据遍历。',
            'concepts' => [
                'yield 会返回 Generator，不会一次性构建完整数组。',
                '适合处理导入文件、大结果集和流式数据。',
                '生成器只能按迭代顺序消费，不能像数组一样随机读取。',
                'Laravel LazyCollection 底层思想与生成器接近。',
            ],
            'code' => <<<'PHP'
function metricRows(int $count): Generator
{
    for ($index = 1; $index <= $count; $index++) {
        yield [
            'code' => 'metric_'.$index,
            'value' => $index * 10,
        ];
    }
}

foreach (metricRows(3) as $row) {
    $rows[] = $row['code'].':'.$row['value'];
}
PHP,
            'output' => $rows,
            'practice' => [
                '用生成器模拟逐行读取 CSV 数据。',
                '对比 range(1, 100000) 和 yield 生成数字的内存差异。',
                '把一个大数组处理函数改成逐条 yield。',
            ],
        ];
    }

    /**
     * @param  array<int, int>  $prices
     */
    private function subtotal(array $prices, int $discount = 0): int
    {
        return array_sum($prices) - $discount;
    }

    private function divide(int $left, int $right): float
    {
        if ($right === 0) {
            throw new \InvalidArgumentException('除数不能为 0');
        }

        return $left / $right;
    }

    /**
     * @return \Generator<int, array{code: string, value: int}>
     */
    private function metricRows(int $count): \Generator
    {
        for ($index = 1; $index <= $count; $index++) {
            yield [
                'code' => 'metric_'.$index,
                'value' => $index * 10,
            ];
        }
    }
}

enum PhpLabLessonStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

final class PhpLabLesson
{
    public PhpLabLessonStatus $status = PhpLabLessonStatus::Draft;

    public function __construct(
        public readonly string $key,
        public readonly string $title,
    ) {}

    public function publish(): void
    {
        $this->status = PhpLabLessonStatus::Published;
    }
}
