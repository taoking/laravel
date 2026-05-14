<?php

namespace App\Console\Commands;

use Attribute;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Number;
use ReflectionClass;
use ReflectionProperty;

class PhpLanguageLabCommand extends Command
{
    protected $signature = 'php:language-lab
        {action=all : Lab action: all, weak-types, cow, references, objects, generator, modern}
        {--rows=5000 : Row count for array and generator labs}';

    protected $description = 'Run PHP language runtime labs for senior interview practice.';

    public function handle(): int
    {
        $actions = match ((string) $this->argument('action')) {
            'all' => ['weak-types', 'cow', 'references', 'objects', 'generator', 'modern'],
            'weak-types' => ['weak-types'],
            'cow' => ['cow'],
            'references' => ['references'],
            'objects' => ['objects'],
            'generator' => ['generator'],
            'modern' => ['modern'],
            default => [],
        };

        if ($actions === []) {
            $this->error('Unsupported action. Use all, weak-types, cow, references, objects, generator, or modern.');

            return self::FAILURE;
        }

        foreach ($actions as $action) {
            match ($action) {
                'weak-types' => $this->weakTypes(),
                'cow' => $this->copyOnWrite(),
                'references' => $this->references(),
                'objects' => $this->objects(),
                'generator' => $this->generator(),
                'modern' => $this->modernFeatures(),
            };
        }

        return self::SUCCESS;
    }

    private function weakTypes(): void
    {
        $this->newLine();
        $this->info('weak-types');

        $coercedInteger = call_user_func([$this, 'acceptInteger'], '42');

        $this->table(['Expression', 'Result', 'Interview Point'], [
            ['0 == "0"', $this->boolLabel(0 == '0'), 'Loose comparison converts compatible scalar values.'],
            ['0 == "foo"', $this->boolLabel(0 == 'foo'), 'PHP 8 changed non-numeric string comparisons.'],
            ['false == []', $this->boolLabel(false == []), 'Different types can still compare equal with ==.'],
            ['acceptInteger("42")', (string) $coercedInteger, 'Without strict_types, scalar parameters can be coerced.'],
        ]);
    }

    private function copyOnWrite(): void
    {
        $this->newLine();
        $this->info('copy-on-write');

        gc_collect_cycles();

        $rows = $this->rowsOption();
        $list = range(1, $rows);
        $map = [
            'metric' => 'revenue_amount',
            'period' => '2026-Q1',
            'status' => 'active',
        ];

        $beforeCopy = memory_get_usage(true);
        $copy = $list;
        $afterAssignment = memory_get_usage(true);
        $copyFirstAfterAssignment = $copy[0];
        $copy[0] = 999;
        $afterMutation = memory_get_usage(true);

        $this->table(['Step', 'Original First', 'Copy First', 'Memory MB'], [
            ['initial list', (string) $list[0], '-', $this->memory($beforeCopy)],
            ['after $copy = $list', (string) $list[0], (string) $copyFirstAfterAssignment, $this->memory($afterAssignment)],
            ['after $copy[0] = 999', (string) $list[0], (string) $copy[0], $this->memory($afterMutation)],
        ]);

        $this->line('array-shape: list first='.$list[0].', map metric='.$map['metric']);
        $this->line('interview-point: assignment shares zval until a write forces copy-on-write.');
    }

    private function references(): void
    {
        $this->newLine();
        $this->info('references');

        $value = ['status' => 'draft'];
        $copy = $value;
        $copy['status'] = 'copied';

        $reference = &$value;
        $reference['status'] = 'referenced';

        $this->table(['Variable', 'Status', 'Interview Point'], [
            ['$value', $value['status'], 'Changed by reference assignment.'],
            ['$copy', $copy['status'], 'Independent after normal assignment mutation.'],
            ['$reference', $reference['status'], 'Alias of $value, not a separated copy.'],
        ]);
    }

    private function objects(): void
    {
        $this->newLine();
        $this->info('objects');

        $counter = new LanguageLabCounter(1);
        $assigned = $counter;
        $assigned->increment();

        $cloned = clone $counter;
        $cloned->increment();

        $this->table(['Variable', 'Value', 'Interview Point'], [
            ['$counter', (string) $counter->value, 'Object assignment copies the handle, not the object payload.'],
            ['$assigned', (string) $assigned->value, 'Same object as $counter.'],
            ['$cloned', (string) $cloned->value, 'clone creates a new object instance.'],
        ]);
    }

    private function generator(): void
    {
        $this->newLine();
        $this->info('generator');

        gc_collect_cycles();

        $rows = $this->rowsOption();
        $arrayBefore = memory_get_usage(true);
        $arrayRows = $this->rowsAsArray($rows);
        $arrayAfter = memory_get_usage(true);
        $arrayCount = count($arrayRows);
        unset($arrayRows);
        gc_collect_cycles();

        $generatorBefore = memory_get_usage(true);
        $generatorCount = 0;
        $total = 0.0;

        foreach ($this->rowsAsGenerator($rows) as $row) {
            $generatorCount++;
            $total += $row['value'];
        }

        $generatorAfter = memory_get_usage(true);

        $this->table(['Mode', 'Rows', 'Memory Before MB', 'Memory After MB', 'Interview Point'], [
            ['array', (string) $arrayCount, $this->memory($arrayBefore), $this->memory($arrayAfter), 'Loads all rows at once.'],
            ['generator', (string) $generatorCount, $this->memory($generatorBefore), $this->memory($generatorAfter), 'Yields one row at a time.'],
        ]);

        $this->line('generator-total='.Number::format($total, 2));
    }

    private function modernFeatures(): void
    {
        $this->newLine();
        $this->info('modern-php');

        $criteria = new LanguageLabCriteria(
            code: 'revenue_amount',
            status: LanguageLabMetricStatus::Active,
            limit: 20,
        );
        $presenter = new LanguageLabInterviewPresenter;
        $multiplier = 2;
        $closure = function (int $value) use ($multiplier): int {
            return $value * $multiplier;
        };
        $arrow = fn (int $value): int => $value * $multiplier;

        $this->table(['Feature', 'Result', 'Project Usage'], [
            ['Enum', $criteria->status->value.' / '.$criteria->status->label(), 'Status whitelist without magic strings.'],
            ['Readonly DTO', $criteria->code.' limit='.$criteria->limit, 'Immutable query criteria.'],
            ['Attribute', $this->attributeSummary($criteria), 'Declarative metadata for fields.'],
            ['Closure', (string) $closure(10), 'Middleware, collection callbacks, strategies.'],
            ['Arrow Function', (string) $arrow(10), 'Short callbacks with lexical capture by value.'],
            ['Union Type', $this->normalizeCode(1001), 'Accept controlled scalar variants explicitly.'],
            ['#[Override]', $presenter->present($criteria), 'Fail fast when parent method contracts drift.'],
        ]);
    }

    private function acceptInteger(int $value): int
    {
        return $value;
    }

    private function boolLabel(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function rowsOption(): int
    {
        return max(1, (int) $this->option('rows'));
    }

    private function memory(int $bytes): string
    {
        return Number::format($bytes / 1024 / 1024, 2);
    }

    /**
     * @return array<int, array{id: int, code: string, value: float}>
     */
    private function rowsAsArray(int $rows): array
    {
        $items = [];

        foreach ($this->rowsAsGenerator($rows) as $row) {
            $items[] = $row;
        }

        return $items;
    }

    /**
     * @return Generator<int, array{id: int, code: string, value: float}>
     */
    private function rowsAsGenerator(int $rows): Generator
    {
        for ($index = 1; $index <= $rows; $index++) {
            yield [
                'id' => $index,
                'code' => 'metric_'.$index,
                'value' => $index / 10,
            ];
        }
    }

    private function normalizeCode(int|string $code): string
    {
        return 'metric-'.strtolower((string) $code);
    }

    private function attributeSummary(LanguageLabCriteria $criteria): string
    {
        $reflection = new ReflectionClass($criteria);

        return collect($reflection->getProperties())
            ->map(function (ReflectionProperty $property): string {
                $attributes = $property->getAttributes(LanguageLabColumn::class);
                $attribute = $attributes[0] ?? null;

                if ($attribute === null) {
                    return $property->getName().': none';
                }

                $column = $attribute->newInstance();

                return $property->getName().': '.$column->label;
            })
            ->implode(', ');
    }
}

final class LanguageLabCounter
{
    public function __construct(public int $value) {}

    public function increment(): void
    {
        $this->value++;
    }
}

enum LanguageLabMetricStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Active => '启用',
            self::Disabled => '停用',
        };
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class LanguageLabColumn
{
    public function __construct(
        public string $label,
        public bool $searchable = false,
    ) {}
}

final readonly class LanguageLabCriteria
{
    public function __construct(
        #[LanguageLabColumn('指标编码', searchable: true)]
        public string $code,
        #[LanguageLabColumn('状态')]
        public LanguageLabMetricStatus $status,
        #[LanguageLabColumn('限制条数')]
        public int $limit,
    ) {}
}

abstract class LanguageLabPresenter
{
    abstract public function present(LanguageLabCriteria $criteria): string;
}

final class LanguageLabInterviewPresenter extends LanguageLabPresenter
{
    #[\Override]
    public function present(LanguageLabCriteria $criteria): string
    {
        return $criteria->code.' is '.$criteria->status->value;
    }
}
