<?php

namespace App\Learning\LaravelInterview\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInterviewToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('interview_examples.token', 'demo-token');

        abort_unless(
            $request->header('X-Interview-Token') === $expectedToken,
            Response::HTTP_FORBIDDEN,
            'Invalid interview example token.',
        );

        return $next($request);
    }
}
