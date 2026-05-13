<?php

namespace App\Learning\LaravelInterview\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Uppercase implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (mb_strtoupper((string) $value) !== (string) $value) {
            $fail("The {$attribute} field must be uppercase.");
        }
    }
}
