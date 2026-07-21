<?php

namespace App\Http\Controllers\Web;

use App\Enums\PtoRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PtoBalance;
use App\Models\PtoPolicy;
use App\Models\PtoRequest;
use App\Services\PtoBalanceService;
use App\Services\WebhookDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PtoRequestController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PtoRequest::class);

        $user = request()->user();
        $canReviewAll = $user->can('pto.manage');

        $filters = request()->validate([
            'status' => ['nullable', Rule::enum(PtoRequestStatus::class)],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'pto_policy_id' => ['nullable', 'integer', 'exists:pto_policies,id'],
            'starts_from' => ['nullable', 'date'],
            'starts_until' => ['nullable', 'date', 'after_or_equal:starts_from'],
        ]);

        $query = PtoRequest::query()
            ->with(['employee', 'policy', 'approver'])
            ->visibleToUser($user);

        $calendarStart = now()->startOfMonth();
        $calendarEnd = now()->addMonths(2)->endOfMonth();
        $calendarRequests = PtoRequest::query()
            ->with(['employee'])
            ->visibleToUser($user)
            ->where('status', PtoRequestStatus::Approved)
            ->whereDate('starts_at', '<=', $calendarEnd)
            ->whereDate('ends_at', '>=', $calendarStart)
            ->orderBy('starts_at')
            ->get();

        $pendingApprovals = PtoRequest::query()
            ->with(['employee', 'policy'])
            ->visibleToUser($user)
            ->where('status', PtoRequestStatus::Pending)
            ->latest()
            ->get()
            ->filter(fn (PtoRequest $ptoRequest): bool => $user->can('approve', $ptoRequest))
            ->values();

        $balances = PtoBalance::query()
            ->with(['employee', 'policy'])
            ->visibleToUser($user)
            ->latest('period_start');

        return view('web.pto.index', [
            'requests' => $query
                ->filterStatus($filters['status'] ?? null)
                ->forEmployee($filters['employee_id'] ?? null)
                ->forPolicy($filters['pto_policy_id'] ?? null)
                ->startingOnOrAfter($filters['starts_from'] ?? null)
                ->startingOnOrBefore($filters['starts_until'] ?? null)
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'balances' => $balances->get(),
            'employees' => $canReviewAll
                ? Employee::orderBy('first_name')->get()
                : Employee::query()
                    ->where('user_id', $user->id)
                    ->when($user->can('pto.approve'), fn (Builder $employee) => $employee->orWhereHas('manager', fn (Builder $manager) => $manager->where('user_id', $user->id)))
                    ->orderBy('first_name')
                    ->get(),
            'pendingApprovals' => $pendingApprovals,
            'calendarMonths' => $this->calendarMonths($calendarStart, $calendarEnd, $calendarRequests),
            'filters' => $filters,
            'policies' => PtoPolicy::orderByDesc('is_default')->orderBy('name')->get(),
            'currentEmployee' => $user->employee,
            'statuses' => PtoRequestStatus::cases(),
            'canAdjustPto' => $user->can('pto.manage') || $user->can('pto.approve'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', PtoRequest::class);

        $user = request()->user();
        $canReviewAll = $user->can('pto.manage');

        return view('web.pto.create', [
            'employees' => $canReviewAll
                ? Employee::orderBy('first_name')->get()
                : Employee::query()
                    ->where('user_id', $user->id)
                    ->when($user->can('pto.approve'), fn (Builder $employee) => $employee->orWhereHas('manager', fn (Builder $manager) => $manager->where('user_id', $user->id)))
                    ->orderBy('first_name')
                    ->get(),
            'policies' => PtoPolicy::orderByDesc('is_default')->orderBy('name')->get(),
            'currentEmployee' => $user->employee,
        ]);
    }

    public function store(Request $request, WebhookDispatcher $webhooks, PtoBalanceService $balances): RedirectResponse
    {
        $this->authorize('create', PtoRequest::class);

        $data = $this->validatedRequest($request);

        if (! $request->user()->can('pto.manage')) {
            abort_if($request->user()->employee?->id !== (int) $data['employee_id'], 403);
        }

        $days = $balances->calculateRequestDays(
            (int) $data['pto_policy_id'],
            $data['starts_at'],
            $data['ends_at'],
            $request->boolean('half_day_start'),
            $request->boolean('half_day_end')
        );

        if ($days <= 0) {
            throw ValidationException::withMessages([
                'starts_at' => 'The selected range does not include PTO working days for this policy.',
            ]);
        }

        $data['days'] = $days;

        $balances->ensureRequestFits((int) $data['employee_id'], (int) $data['pto_policy_id'], $data['starts_at'], (float) $data['days']);

        $ptoRequest = PtoRequest::create($data + [
            'status' => PtoRequestStatus::Pending,
        ]);

        $balances->recordRequested($ptoRequest);
        $webhooks->dispatch('pto.requested', ['pto_request_id' => $ptoRequest->id]);

        return redirect()->route('pto.index')->with('status', 'PTO request submitted.');
    }

    public function adjust(Request $request, PtoBalanceService $balances): RedirectResponse
    {
        $this->authorize('viewAny', PtoRequest::class);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'pto_policy_id' => ['required', 'integer', 'exists:pto_policies,id'],
            'effective_date' => ['required', 'date'],
            'days' => ['required', 'numeric', 'min:-365', 'max:365', 'not_in:0', 'multiple_of:0.5'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        abort_unless($this->canAdjustEmployee($request, (int) $data['employee_id']), 403);

        $balances->recordAdjustment(
            (int) $data['employee_id'],
            (int) $data['pto_policy_id'],
            $data['effective_date'],
            (float) $data['days'],
            $request->user()->id,
            $data['reason']
        );

        return redirect()->route('pto.index')->with('status', 'PTO balance adjusted.');
    }

    public function approve(Request $request, PtoRequest $ptoRequest, WebhookDispatcher $webhooks, PtoBalanceService $balances): RedirectResponse
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

        return redirect()->route('pto.index')->with('status', 'PTO request approved.');
    }

    public function reject(Request $request, PtoRequest $ptoRequest, WebhookDispatcher $webhooks, PtoBalanceService $balances): RedirectResponse
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

        return redirect()->route('pto.index')->with('status', 'PTO request rejected.');
    }

    public function cancel(Request $request, PtoRequest $ptoRequest, WebhookDispatcher $webhooks, PtoBalanceService $balances): RedirectResponse
    {
        $this->authorize('cancel', $ptoRequest);

        $ptoRequest->update([
            'status' => PtoRequestStatus::Canceled,
            'decision_notes' => $request->input('decision_notes'),
            'decided_at' => now(),
        ]);

        $balances->recordCanceled($ptoRequest);
        $webhooks->dispatch('pto.canceled', ['pto_request_id' => $ptoRequest->id]);

        return redirect()->route('pto.index')->with('status', 'PTO request canceled.');
    }

    private function validatedRequest(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'pto_policy_id' => ['required', 'integer', 'exists:pto_policies,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'half_day_start' => ['sometimes', 'boolean'],
            'half_day_end' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'days' => ['prohibited'],
            'status' => ['prohibited'],
            'approver_id' => ['prohibited'],
            'decision_notes' => ['prohibited'],
            'decided_at' => ['prohibited'],
        ]);
    }

    private function canAdjustEmployee(Request $request, int $employeeId): bool
    {
        if ($request->user()->can('pto.manage')) {
            return true;
        }

        if (! $request->user()->can('pto.approve')) {
            return false;
        }

        return Employee::query()
            ->whereKey($employeeId)
            ->whereHas('manager', fn (Builder $manager) => $manager->where('user_id', $request->user()->id))
            ->exists();
    }

    private function calendarMonths(Carbon $calendarStart, Carbon $calendarEnd, Collection $requests): array
    {
        $months = [];

        for ($monthOffset = 0; $monthOffset < 3; $monthOffset++) {
            $monthStart = $calendarStart->copy()->addMonths($monthOffset)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $days = [];

            for ($date = $monthStart->copy(); $date->lte($monthEnd); $date->addDay()) {
                $dateKey = $date->toDateString();
                $events = $requests
                    ->filter(fn (PtoRequest $request): bool => $request->starts_at?->copy()->startOfDay()->lte($date) && $request->ends_at?->copy()->startOfDay()->gte($date))
                    ->map(fn (PtoRequest $request): array => [
                        'name' => $request->employee?->full_name ?? 'Unknown employee',
                        'initials' => $this->employeeInitials($request->employee),
                    ])
                    ->values()
                    ->all();

                $days[] = [
                    'date' => $dateKey,
                    'day' => $date->day,
                    'events' => $events,
                ];
            }

            $months[] = [
                'name' => $monthStart->format('F Y'),
                'leadingBlanks' => $monthStart->dayOfWeek,
                'days' => $days,
            ];
        }

        return $months;
    }

    private function employeeInitials(?Employee $employee): string
    {
        if (! $employee) {
            return '?';
        }

        return str(substr((string) $employee->first_name, 0, 1).substr((string) $employee->last_name, 0, 1))->upper()->toString();
    }
}
