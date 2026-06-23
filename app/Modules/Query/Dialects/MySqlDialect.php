<?php

namespace App\Modules\Query\Dialects;

use App\Modules\DataSource\Services\IdentifierGuard;
use InvalidArgumentException;

class MySqlDialect implements SqlDialectInterface
{
    /**
     * @var list<string>
     */
    private const FUNCTIONS = ['sum', 'avg', 'count', 'countDistinct', 'max', 'min'];

    public function quoteIdentifier(string $identifier): string
    {
        if (! IdentifierGuard::isSafe($identifier)) {
            throw new InvalidArgumentException("Unsafe SQL identifier [{$identifier}].");
        }

        return '`'.$identifier.'`';
    }

    public function compileDateGrain(string $field, string $grain): string
    {
        return match ($grain) {
            'year' => "year({$field})",
            'quarter' => "concat(year({$field}), '-Q', quarter({$field}))",
            'month' => "date_format({$field}, '%Y-%m')",
            'week' => "yearweek({$field}, 3)",
            'day' => "date_format({$field}, '%Y-%m-%d')",
            'hour' => "date_format({$field}, '%Y-%m-%d %H:00:00')",
            'minute' => "date_format({$field}, '%Y-%m-%d %H:%i:00')",
            default => $field,
        };
    }

    public function compileLimit(int $limit, ?int $offset = null): string
    {
        $limit = max(1, $limit);
        $offset = max(0, (int) ($offset ?? 0));

        return "limit {$limit} offset {$offset}";
    }

    public function compileAggregate(string $function, string $field): string
    {
        if (! $this->supportsFunction($function)) {
            throw new InvalidArgumentException("Unsupported aggregate function [{$function}].");
        }

        return $function === 'countDistinct'
            ? "count(distinct {$field})"
            : "{$function}({$field})";
    }

    public function supportsFunction(string $function): bool
    {
        return in_array($function, self::FUNCTIONS, true);
    }

    public function getName(): string
    {
        return 'mysql';
    }
}
