<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompensationHistoryRequest;
use App\Http\Resources\CompensationHistoryResource;
use App\Models\Employee;
use App\Models\SystemSetting;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompensationHistoryController extends Controller
{
    public function index(Request $request, Employee $employee): AnonymousResourceCollection
    {
        $this->authorize('viewCompensation', $employee);

        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:80'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        return CompensationHistoryResource::collection(
            $employee->compensationHistories()
                ->filterType($filters['type'] ?? null)
                ->effectiveOnOrAfter($filters['effective_from'] ?? null)
                ->effectiveOnOrBefore($filters['effective_until'] ?? null)
                ->latest('effective_date')
                ->paginate()
                ->withQueryString()
        );
    }

    public function store(Employee $employee, CompensationHistoryRequest $request, WebhookDispatcher $webhooks): CompensationHistoryResource
    {
        $this->authorize('manageCompensation', $employee);

        $history = $employee->compensationHistories()->create($request->validated() + [
            'created_by_id' => $request->user()->id,
            'currency' => SystemSetting::mainCurrency(),
            'type' => $request->input('type', 'salary'),
        ]);

        $webhooks->dispatch('compensation.created', [
            'employee_id' => $employee->id,
            'compensation_history_id' => $history->id,
        ]);

        return new CompensationHistoryResource($history);
    }
}
