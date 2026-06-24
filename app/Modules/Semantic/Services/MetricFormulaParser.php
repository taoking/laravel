<?php

namespace App\Modules\Semantic\Services;

use InvalidArgumentException;

class MetricFormulaParser
{
    /**
     * @param  list<string>  $allowedMetricCodes
     * @return array{valid: bool, dependencies: list<string>, tokens: list<string>}
     */
    public function validate(string $formula, array $allowedMetricCodes): array
    {
        $tokens = $this->tokens($formula);
        $dependencies = $this->dependenciesFromTokens($tokens);
        $unknown = array_values(array_diff($dependencies, $allowedMetricCodes));

        if ($unknown !== []) {
            throw new InvalidArgumentException('Formula references unknown metric code: '.implode(', ', $unknown).'.');
        }

        $this->toRpn($tokens);

        return [
            'valid' => true,
            'dependencies' => $dependencies,
            'tokens' => $tokens,
        ];
    }

    /**
     * @return list<string>
     */
    public function dependencies(string $formula): array
    {
        return $this->dependenciesFromTokens($this->tokens($formula));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function evaluate(string $formula, array $values): float|int|null
    {
        $stack = [];

        foreach ($this->toRpn($this->tokens($formula)) as $token) {
            if ($this->isOperator($token)) {
                if (count($stack) < 2) {
                    throw new InvalidArgumentException('Formula operator is missing operands.');
                }

                $right = array_pop($stack);
                $left = array_pop($stack);

                if ($left === null || $right === null) {
                    $stack[] = null;

                    continue;
                }

                $stack[] = match ($token) {
                    '+' => $left + $right,
                    '-' => $left - $right,
                    '*' => $left * $right,
                    '/' => (float) $right == 0.0 ? null : $left / $right,
                };

                continue;
            }

            if ($this->isNumber($token)) {
                $stack[] = str_contains($token, '.') ? (float) $token : (int) $token;

                continue;
            }

            $stack[] = array_key_exists($token, $values) && is_numeric($values[$token])
                ? (float) $values[$token]
                : null;
        }

        if (count($stack) !== 1) {
            throw new InvalidArgumentException('Formula could not be evaluated.');
        }

        return $stack[0];
    }

    /**
     * @return list<string>
     */
    private function tokens(string $formula): array
    {
        $tokens = [];
        $length = strlen($formula);
        $position = 0;

        while ($position < $length) {
            $char = $formula[$position];

            if (ctype_space($char)) {
                $position++;

                continue;
            }

            if (preg_match('/[A-Za-z_]/', $char) === 1) {
                $start = $position;
                $position++;

                while ($position < $length && preg_match('/[A-Za-z0-9_]/', $formula[$position]) === 1) {
                    $position++;
                }

                $tokens[] = substr($formula, $start, $position - $start);

                continue;
            }

            if (ctype_digit($char)) {
                $start = $position;
                $position++;

                while ($position < $length && (ctype_digit($formula[$position]) || $formula[$position] === '.')) {
                    $position++;
                }

                $number = substr($formula, $start, $position - $start);

                if (! $this->isNumber($number)) {
                    throw new InvalidArgumentException("Invalid number token [{$number}].");
                }

                $tokens[] = $number;

                continue;
            }

            if (in_array($char, ['+', '-', '*', '/', '(', ')'], true)) {
                $tokens[] = $char;
                $position++;

                continue;
            }

            throw new InvalidArgumentException('Formula contains unsupported character.');
        }

        if ($tokens === []) {
            throw new InvalidArgumentException('Formula is required.');
        }

        return $tokens;
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function dependenciesFromTokens(array $tokens): array
    {
        return collect($tokens)
            ->filter(fn (string $token): bool => preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $token) === 1)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function toRpn(array $tokens): array
    {
        $output = [];
        $operators = [];
        $expectValue = true;

        foreach ($tokens as $token) {
            if ($this->isNumber($token) || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $token) === 1) {
                if (! $expectValue) {
                    throw new InvalidArgumentException('Formula is missing an operator.');
                }

                $output[] = $token;
                $expectValue = false;

                continue;
            }

            if ($token === '(') {
                if (! $expectValue) {
                    throw new InvalidArgumentException('Formula is missing an operator before a parenthesis.');
                }

                $operators[] = $token;

                continue;
            }

            if ($token === ')') {
                if ($expectValue) {
                    throw new InvalidArgumentException('Formula has an empty or incomplete parenthesis.');
                }

                while ($operators !== [] && end($operators) !== '(') {
                    $output[] = array_pop($operators);
                }

                if ($operators === []) {
                    throw new InvalidArgumentException('Formula has unmatched parentheses.');
                }

                array_pop($operators);
                $expectValue = false;

                continue;
            }

            if ($this->isOperator($token)) {
                if ($expectValue) {
                    throw new InvalidArgumentException('Formula operator is missing a left operand.');
                }

                while ($operators !== [] && $this->isOperator((string) end($operators)) && $this->precedence((string) end($operators)) >= $this->precedence($token)) {
                    $output[] = array_pop($operators);
                }

                $operators[] = $token;
                $expectValue = true;
            }
        }

        if ($expectValue) {
            throw new InvalidArgumentException('Formula is incomplete.');
        }

        while ($operators !== []) {
            $operator = array_pop($operators);

            if ($operator === '(') {
                throw new InvalidArgumentException('Formula has unmatched parentheses.');
            }

            $output[] = $operator;
        }

        return $output;
    }

    private function isOperator(string $token): bool
    {
        return in_array($token, ['+', '-', '*', '/'], true);
    }

    private function precedence(string $operator): int
    {
        return in_array($operator, ['*', '/'], true) ? 2 : 1;
    }

    private function isNumber(string $token): bool
    {
        return preg_match('/\A\d+(?:\.\d+)?\z/', $token) === 1;
    }
}
