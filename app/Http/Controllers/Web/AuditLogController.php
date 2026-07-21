<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = request()->validate([
            'event' => ['nullable', 'string', 'max:120'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'auditable_type' => ['nullable', 'string', 'max:255'],
            'auditable_id' => ['nullable', 'string', 'max:100'],
            'occurred_from' => ['nullable', 'date'],
            'occurred_until' => ['nullable', 'date', 'after_or_equal:occurred_from'],
        ]);

        return view('web.audit.index', [
            'actorOptions' => User::query()->whereIn('id', AuditLog::query()->whereNotNull('actor_id')->select('actor_id'))->orderBy('name')->get(),
            'auditLogs' => AuditLog::query()
                ->forEvent($filters['event'] ?? null)
                ->forActor($filters['actor_id'] ?? null)
                ->forAuditableType($filters['auditable_type'] ?? null)
                ->forAuditableId($filters['auditable_id'] ?? null)
                ->occurredOnOrAfter($filters['occurred_from'] ?? null)
                ->occurredOnOrBefore($filters['occurred_until'] ?? null)
                ->latest('occurred_at')
                ->paginate(30)
                ->withQueryString(),
            'auditableTypes' => AuditLog::query()->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'filters' => $filters,
        ]);
    }
}
