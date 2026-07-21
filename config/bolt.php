<?php

use App\Enums\PermissionName;

$webhookEventCatalog = [
    'employee.created' => 'Employee record created.',
    'employee.updated' => 'Employee record updated.',
    'compensation.created' => 'Compensation history entry created.',
    'benefit.created' => 'Benefit or bonus history entry created.',
    'pto.requested' => 'PTO request submitted.',
    'pto.approved' => 'PTO request approved.',
    'pto.rejected' => 'PTO request rejected.',
    'pto.canceled' => 'PTO request canceled.',
    'pto_policy.created' => 'PTO policy created.',
    'pto_policy.updated' => 'PTO policy updated.',
    'attachment.created' => 'Private attachment uploaded.',
    'knowledge_article.created' => 'Knowledge article created.',
    'knowledge_article.updated' => 'Knowledge article updated.',
    'asset.created' => 'Asset record created.',
    'asset.updated' => 'Asset record updated.',
    'asset.assigned' => 'Asset assigned to an employee.',
    'asset.returned' => 'Asset returned from an employee.',
    'webhook.test' => 'Test event for validating endpoint delivery.',
];

return [
    'api' => [
        'rate_limit' => env('BOLT_API_RATE_LIMIT', '120,1'),
        'idempotency_ttl_hours' => env('BOLT_IDEMPOTENCY_TTL_HOURS', 24),
    ],

    'webhooks' => [
        'timeout_seconds' => env('BOLT_WEBHOOK_TIMEOUT', 10),
        'max_attempts' => env('BOLT_WEBHOOK_MAX_ATTEMPTS', 5),
        'event_catalog' => $webhookEventCatalog,
        'events' => array_keys($webhookEventCatalog),
    ],

    'retention' => [
        'audit_days' => env('BOLT_AUDIT_RETENTION_DAYS', 730),
        'webhook_delivery_days' => env('BOLT_WEBHOOK_DELIVERY_RETENTION_DAYS', 90),
    ],

    'permissions' => array_map(
        fn (PermissionName $permission): string => $permission->value,
        PermissionName::cases(),
    ),
];
