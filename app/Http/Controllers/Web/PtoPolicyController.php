<?php

namespace App\Http\Controllers\Web;

use App\Enums\PtoAccrualType;
use App\Http\Controllers\Controller;
use App\Models\PtoPolicy;
use App\Services\WebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PtoPolicyController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PtoPolicy::class);

        return view('web.pto-policies.index', [
            'policies' => PtoPolicy::query()
                ->withCount(['balances'])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', PtoPolicy::class);

        return view('web.pto-policies.form', [
            'accrualTypes' => PtoAccrualType::cases(),
            'approvalStrategies' => $this->approvalStrategies(),
            'accumulationFrequencies' => PtoPolicy::ACCUMULATION_FREQUENCIES,
            'workingDayOptions' => PtoPolicy::WEEKDAYS,
            'policy' => new PtoPolicy([
                'annual_allowance_days' => 15,
                'accrual_type' => PtoAccrualType::AnnualGrant,
                'accumulation_frequency' => 'monthly',
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'holidays' => [],
                'allow_negative_balance' => false,
                'carryover_days' => 5,
                'approval_strategy' => 'manager_then_hr',
            ]),
        ]);
    }

    public function store(Request $request, WebhookDispatcher $webhooks): RedirectResponse
    {
        $this->authorize('create', PtoPolicy::class);

        $policy = DB::transaction(function () use ($request): PtoPolicy {
            $data = $this->validatedPolicy($request);

            if ($request->boolean('is_default')) {
                PtoPolicy::query()->update(['is_default' => false]);
            }

            return PtoPolicy::create($data + [
                'is_default' => $request->boolean('is_default'),
            ]);
        });

        $webhooks->dispatch('pto_policy.created', ['pto_policy_id' => $policy->id]);

        return redirect()->route('pto-policies.index')->with('status', 'PTO policy created.');
    }

    public function edit(PtoPolicy $ptoPolicy): View
    {
        $this->authorize('update', $ptoPolicy);

        return view('web.pto-policies.form', [
            'accrualTypes' => PtoAccrualType::cases(),
            'accumulationFrequencies' => PtoPolicy::ACCUMULATION_FREQUENCIES,
            'workingDayOptions' => PtoPolicy::WEEKDAYS,
            'approvalStrategies' => $this->approvalStrategies(),
            'policy' => $ptoPolicy,
        ]);
    }

    public function update(Request $request, PtoPolicy $ptoPolicy, WebhookDispatcher $webhooks): RedirectResponse
    {
        $this->authorize('update', $ptoPolicy);

        DB::transaction(function () use ($request, $ptoPolicy): void {
            $data = $this->validatedPolicy($request, $ptoPolicy);

            if ($request->boolean('is_default')) {
                PtoPolicy::query()
                    ->whereKeyNot($ptoPolicy->id)
                    ->update(['is_default' => false]);
            }

            $ptoPolicy->update($data + [
                'is_default' => $request->boolean('is_default'),
            ]);
        });

        $webhooks->dispatch('pto_policy.updated', ['pto_policy_id' => $ptoPolicy->id]);

        return redirect()->route('pto-policies.index')->with('status', 'PTO policy updated.');
    }

    private function validatedPolicy(Request $request, ?PtoPolicy $policy = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('pto_policies', 'name')->ignore($policy)],
            'annual_allowance_days' => ['required', 'numeric', 'min:0', 'max:365', 'multiple_of:0.5'],
            'accrual_type' => ['required', Rule::enum(PtoAccrualType::class)],
            'accumulation_frequency' => ['required', Rule::in(array_keys(PtoPolicy::ACCUMULATION_FREQUENCIES))],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', Rule::in(array_keys(PtoPolicy::WEEKDAYS))],
            'holidays' => ['nullable', 'string', 'max:4000'],
            'allow_negative_balance' => ['sometimes', 'boolean'],
            'carryover_days' => ['required', 'numeric', 'min:0', 'max:365', 'multiple_of:0.5'],
            'approval_strategy' => ['required', 'string', Rule::in(array_keys($this->approvalStrategies()))],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $data['working_days'] = array_values(array_unique($data['working_days']));
        $data['holidays'] = $this->holidayDates($request->input('holidays'));
        $data['allow_negative_balance'] = $request->boolean('allow_negative_balance');

        return $data;
    }

    private function holidayDates(?string $holidays): array
    {
        if (blank($holidays)) {
            return [];
        }

        $dates = collect(preg_split('/[\s,]+/', $holidays, flags: PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $date): string => trim($date))
            ->unique()
            ->values();

        $invalid = $dates->first(fn (string $date): bool => ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date));

        if ($invalid) {
            throw ValidationException::withMessages([
                'holidays' => 'Holiday dates must use YYYY-MM-DD format.',
            ]);
        }

        return $dates->all();
    }

    private function approvalStrategies(): array
    {
        return [
            'manager_then_hr' => 'Manager, then HR',
            'manager_only' => 'Manager only',
            'hr_only' => 'HR only',
        ];
    }
}
