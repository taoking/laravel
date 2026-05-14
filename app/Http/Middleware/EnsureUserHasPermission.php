<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return $request->is('api/*')
                ? ApiResponse::error('Unauthenticated.', 401)
                : redirect()->guest(route('login'));
        }

        if (! $user->hasPermission($permission)) {
            return $request->is('api/*')
                ? ApiResponse::error('Forbidden.', 403)
                : abort(403);
        }

        return $next($request);
    }
}
