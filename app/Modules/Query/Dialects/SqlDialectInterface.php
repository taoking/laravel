<?php

namespace App\Modules\Query\Dialects;

interface SqlDialectInterface
{
    public function quoteIdentifier(string $identifier): string;

    public function compileDateGrain(string $field, string $grain): string;

    public function compileLimit(int $limit, ?int $offset = null): string;

    public function compileAggregate(string $function, string $field): string;

    public function supportsFunction(string $function): bool;

    public function getName(): string;
}
