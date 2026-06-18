<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Audit\Services\LoginLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly LoginLogService $loginLogService) {}

    /**
     * @param  array{email: string, password: string, device_name?: string|null}  $payload
     * @return array{token: string, token_type: string, user: User}
     */
    public function login(array $payload, ?string $ip = null, ?string $userAgent = null): array
    {
        $user = User::query()
            ->where('email', $payload['email'])
            ->first();

        if (! $user || ! Hash::check($payload['password'], $user->password)) {
            $this->loginLogService->record($user, $ip, $userAgent, 'failed', 'Invalid credentials.');

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        if ($user->status !== 'active') {
            $this->loginLogService->record($user, $ip, $userAgent, 'failed', 'User account disabled.');

            throw ValidationException::withMessages([
                'email' => ['The user account is disabled.'],
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $token = $user->createToken($payload['device_name'] ?? 'api-token')->plainTextToken;
        $this->loginLogService->record($user, $ip, $userAgent, 'success', 'Login successful.');

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load(['organization', 'department', 'roles.permissions']),
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }
    }
}
