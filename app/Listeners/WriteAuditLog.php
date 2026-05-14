<?php

namespace App\Listeners;

use App\Domains\Audit\Models\AuditLog;
use App\Events\AuditEvent;

class WriteAuditLog
{
    public function handle(AuditEvent $event): void
    {
        AuditLog::query()->create([
            'user_id' => $event->request->user()?->id,
            'action' => $event->action,
            'resource_type' => $event->resourceType,
            'resource_id' => $event->resourceId,
            'ip_address' => $event->request->ip(),
            'trace_id' => $event->request->attributes->get('trace_id'),
            'user_agent' => $event->request->userAgent(),
            'metadata' => $event->metadata,
        ]);
    }
}
