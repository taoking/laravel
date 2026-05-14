<?php

namespace App\Support\Security;

class SensitiveDataMasker
{
    private const MASK = '[FILTERED]';

    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'authorization',
        'cookie',
        'password',
        'password_confirmation',
        'secret',
        'token',
        'api_token',
        'access_token',
        'refresh_token',
        'signature',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function mask(array $payload): array
    {
        $masked = [];

        foreach ($payload as $key => $value) {
            $masked[$key] = $this->isSensitiveKey((string) $key)
                ? self::MASK
                : $this->maskValue($value);
        }

        return $masked;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '.'], '_', $key));

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || str_ends_with($normalized, '_'.$sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    private function maskValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return $this->mask($value);
    }
}
