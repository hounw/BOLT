<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BenefitHistoryRequest;
use App\Http\Resources\BenefitHistoryResource;
use App\Models\Employee;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BenefitHistoryController extends Controller
{
    public function index(Request $request, Employee $employee): AnonymousResourceCollection
    {
        $this->authorize('viewBenefits', $employee);

        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:120'],
            'starts_from' => ['nullable', 'date'],
            'starts_until' => ['nullable', 'date', 'after_or_equal:starts_from'],
        ]);

        return BenefitHistoryResource::collection(
            $employee->benefitHistories()
                ->filterType($filters['type'] ?? null)
                ->startingOnOrAfter($filters['starts_from'] ?? null)
                ->startingOnOrBefore($filters['starts_until'] ?? null)
                ->latest('starts_on')
                ->latest()
                ->paginate()
                ->withQueryString()
        );
    }

    public function store(Employee $employee, BenefitHistoryRequest $request, WebhookDispatcher $webhooks): BenefitHistoryResource
    {
        $this->authorize('manageBenefits', $employee);

        $history = $employee->benefitHistories()->create($request->validated() + [
            'created_by_id' => $request->user()->id,
        ]);

        $webhooks->dispatch('benefit.created', [
            'employee_id' => $employee->id,
            'benefit_history_id' => $history->id,
        ]);

        return new BenefitHistoryResource($history);
    }
}
