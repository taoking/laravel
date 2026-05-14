<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

class AuditEvent
{
    use Dispatchable;

    public function __construct(
        public string $action,
        public ?string $resourceType,
        public ?int $resourceId,
        public array $metadata,
        public Request $request,
    ) {}
}
