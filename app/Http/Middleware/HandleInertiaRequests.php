<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    #[\Override]
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'app' => [
                'name' => config('app.name', 'Laravel Metrics Platform'),
                'environment' => app()->environment(),
                'trace_id' => $request->attributes->get('trace_id'),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
        ]);
    }
}
