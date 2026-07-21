<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:120'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'auditable_type' => ['nullable', 'string', 'max:255'],
            'auditable_id' => ['nullable', 'string', 'max:100'],
            'occurred_from' => ['nullable', 'date'],
            'occurred_until' => ['nullable', 'date', 'after_or_equal:occurred_from'],
        ]);

        return AuditLogResource::collection(
            AuditLog::query()
                ->forEvent($filters['event'] ?? null)
                ->forActor($filters['actor_id'] ?? null)
                ->forAuditableType($filters['auditable_type'] ?? null)
                ->forAuditableId($filters['auditable_id'] ?? null)
                ->occurredOnOrAfter($filters['occurred_from'] ?? null)
                ->occurredOnOrBefore($filters['occurred_until'] ?? null)
                ->latest('occurred_at')
                ->paginate()
                ->withQueryString()
        );
    }
}
