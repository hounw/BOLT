<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PtoRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PtoDecisionRequest;
use App\Http\Requests\Api\PtoRequestStoreRequest;
use App\Http\Resources\PtoRequestResource;
use App\Models\PtoRequest;
use App\Services\PtoBalanceService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PtoRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PtoRequest::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PtoRequestStatus::class)],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'pto_policy_id' => ['nullable', 'integer', 'exists:pto_policies,id'],
            'starts_from' => ['nullable', 'date'],
            'starts_until' => ['nullable', 'date', 'after_or_equal:starts_from'],
        ]);

        $query = PtoRequest::query()
            ->with(['employee', 'policy', 'approver'])
            ->visibleToUser($request->user());

        return PtoRequestResource::collection(
            $query
                ->filterStatus($filters['status'] ?? null)
                ->forEmployee($filters['employee_id'] ?? null)
                ->forPolicy($filters['pto_policy_id'] ?? null)
                ->startingOnOrAfter($filters['starts_from'] ?? null)
                ->startingOnOrBefore($filters['starts_until'] ?? null)
                ->latest()
                ->paginate()
                ->withQueryString()
        );
    }

    public function store(PtoRequestStoreRequest $request, WebhookDispatcher $webhooks, PtoBalanceService $balances): PtoRequestResource
    {
        $this->authorize('create', PtoRequest::class);

        if (! $request->user()->can('pto.manage')) {
            abort_if($request->user()->employee?->id !== (int) $request->input('employee_id'), 403);
        }

        $data = $request->validated();

        $balances->ensureRequestFits((int) $data['employee_id'], (int) $data['pto_policy_id'], $data['starts_at'], (float) $data['days']);

        $ptoRequest = PtoRequest::create($data + ['status' => PtoRequestStatus::Pending]);
        $balances->recordRequested($ptoRequest);
        $webhooks->dispatch('pto.requested', ['pto_request_id' => $ptoRequest->id]);

        return new PtoRequestResource($ptoRequest);
    }

    public function approve(PtoDecisionRequest $request, PtoRequest $ptoRequest, WebhookDispatcher $webhooks, PtoBalanceService $balances): PtoRequestResource
    {
        $this->authorize('approve', $ptoRequest);

        $ptoRequest->update([
            'status' => PtoRequestStatus::Approved,
            'approver_id' => $request->user()->id,
            'decision_notes' => $request->input('decision_notes'),
            'decided_at' => now(),
        ]);

        $balances->recordApproved($ptoRequest);
        $webhooks->dispatch('pto.approved', ['pto_request_id' => $ptoRequest->id]);

        return new PtoRequestResource($ptoRequest);
    }

    public function reject(PtoDecisionRequest $request, PtoRequest $ptoRequest, WebhookDispatcher $webhooks, PtoBalanceService $balances): PtoRequestResource
    {
        $this->authorize('approve', $ptoRequest);

        $ptoRequest->update([
            'status' => PtoRequestStatus::Rejected,
            'approver_id' => $request->user()->id,
            'decision_notes' => $request->input('decision_notes'),
            'decided_at' => now(),
        ]);

        $balances->recordRejected($ptoRequest);
        $webhooks->dispatch('pto.rejected', ['pto_request_id' => $ptoRequest->id]);

        return new PtoRequestResource($ptoRequest);
    }

    public function cancel(Request $request, PtoRequest $ptoRequest, WebhookDispatcher $webhooks, PtoBalanceService $balances): PtoRequestResource
    {
        $this->authorize('cancel', $ptoRequest);

        $ptoRequest->update([
            'status' => PtoRequestStatus::Canceled,
            'decision_notes' => $request->input('decision_notes'),
            'decided_at' => now(),
        ]);

        $balances->recordCanceled($ptoRequest);
        $webhooks->dispatch('pto.canceled', ['pto_request_id' => $ptoRequest->id]);

        return new PtoRequestResource($ptoRequest);
    }
}
