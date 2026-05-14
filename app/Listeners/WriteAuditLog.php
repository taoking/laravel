<?php

namespace App\Listeners;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Messaging\KafkaProducer;
use App\Events\AuditEvent;
use App\Support\Security\SensitiveDataMasker;
use Throwable;

class WriteAuditLog
{
    public function __construct(
        private readonly KafkaProducer $producer,
        private readonly SensitiveDataMasker $masker,
    ) {}

    public function handle(AuditEvent $event): void
    {
        $auditLog = AuditLog::query()->create([
            'user_id' => $event->request->user()?->id,
            'action' => $event->action,
            'resource_type' => $event->resourceType,
            'resource_id' => $event->resourceId,
            'ip_address' => $event->request->ip(),
            'trace_id' => $event->request->attributes->get('trace_id'),
            'user_agent' => $event->request->userAgent(),
            'metadata' => $this->masker->mask($event->metadata),
        ]);

        try {
            $this->producer->publishEvent('audit.event.created', [
                'action' => $event->action,
                'resource_type' => $event->resourceType ?: 'unknown',
                'resource_id' => $event->resourceId ?: $auditLog->id,
                'operator_id' => $event->request->user()?->id,
                'audit_log_id' => $auditLog->id,
            ], traceId: (string) $event->request->attributes->get('trace_id'));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
