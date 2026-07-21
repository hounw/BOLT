<?php

namespace App\Services;

use App\Models\PtoAdjustment;
use App\Models\PtoBalance;
use App\Models\PtoPolicy;
use App\Models\PtoRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PtoBalanceService
{
    public function ensureRequestFits(int $employeeId, int $policyId, CarbonInterface|string $startsAt, float $days): void
    {
        $policy = PtoPolicy::findOrFail($policyId);

        if ($policy->allow_negative_balance) {
            return;
        }

        $balance = $this->findBalance($employeeId, $policyId, $startsAt);
        $remaining = $balance
            ? (float) $balance->available_days - (float) $balance->pending_days
            : $this->periodAllowanceDays($policy);

        if ($days > $remaining) {
            throw ValidationException::withMessages([
                'days' => 'The requested PTO days exceed the remaining balance.',
            ]);
        }
    }

    public function recordRequested(PtoRequest $ptoRequest): void
    {
        $this->adjust($ptoRequest, pendingDelta: (float) $ptoRequest->days);
    }

    public function recordApproved(PtoRequest $ptoRequest): void
    {
        $days = (float) $ptoRequest->days;

        $this->adjust($ptoRequest, pendingDelta: -1 * $days, usedDelta: $days, availableDelta: -1 * $days);
    }

    public function recordRejected(PtoRequest $ptoRequest): void
    {
        $this->adjust($ptoRequest, pendingDelta: -1 * (float) $ptoRequest->days);
    }

    public function recordCanceled(PtoRequest $ptoRequest): void
    {
        $this->adjust($ptoRequest, pendingDelta: -1 * (float) $ptoRequest->days);
    }

    public function calculateRequestDays(int $policyId, CarbonInterface|string $startsAt, CarbonInterface|string $endsAt, bool $halfStart = false, bool $halfEnd = false): float
    {
        $policy = PtoPolicy::findOrFail($policyId);
        $start = Carbon::parse($startsAt)->startOfDay();
        $end = Carbon::parse($endsAt)->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        $workingDays = $policy->workingDays();
        $holidays = $policy->holidayDates();
        $countedDates = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $weekday = strtolower($date->englishDayOfWeek);

            if (in_array($weekday, $workingDays, true) && ! in_array($date->toDateString(), $holidays, true)) {
                $countedDates[] = $date->toDateString();
            }
        }

        if ($countedDates === []) {
            return 0;
        }

        $days = count($countedDates);

        if ($halfStart && in_array($start->toDateString(), $countedDates, true)) {
            $days -= 0.5;
        }

        if ($halfEnd && $end->ne($start) && in_array($end->toDateString(), $countedDates, true)) {
            $days -= 0.5;
        }

        return max(0.5, $days);
    }

    public function recordAdjustment(int $employeeId, int $policyId, CarbonInterface|string $effectiveDate, float $days, ?int $adjustedById = null, ?string $reason = null): PtoAdjustment
    {
        $balance = $this->balanceFor($employeeId, $policyId, $effectiveDate);

        $balance->forceFill([
            'available_days' => (float) $balance->available_days + $days,
        ])->save();

        return PtoAdjustment::create([
            'employee_id' => $employeeId,
            'pto_policy_id' => $policyId,
            'adjusted_by_id' => $adjustedById,
            'effective_date' => Carbon::parse($effectiveDate)->toDateString(),
            'days' => $days,
            'reason' => $reason,
        ]);
    }

    private function adjust(PtoRequest $ptoRequest, float $pendingDelta = 0, float $usedDelta = 0, float $availableDelta = 0): void
    {
        $balance = $this->balanceFor($ptoRequest->employee_id, $ptoRequest->pto_policy_id, $ptoRequest->starts_at);
        $allowNegativeBalance = (bool) $ptoRequest->policy()->value('allow_negative_balance');
        $availableDays = (float) $balance->available_days + $availableDelta;

        $balance->forceFill([
            'available_days' => $allowNegativeBalance ? $availableDays : max(0, $availableDays),
            'pending_days' => max(0, (float) $balance->pending_days + $pendingDelta),
            'used_days' => max(0, (float) $balance->used_days + $usedDelta),
        ])->save();
    }

    private function balanceFor(int $employeeId, int $policyId, CarbonInterface|string $startsAt): PtoBalance
    {
        $balance = $this->findBalance($employeeId, $policyId, $startsAt);

        if ($balance) {
            return $balance;
        }

        $policy = PtoPolicy::findOrFail($policyId);
        [$periodStart, $periodEnd] = $this->periodFor($policy, $startsAt);

        return PtoBalance::create([
            'employee_id' => $employeeId,
            'pto_policy_id' => $policyId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'available_days' => $this->periodAllowanceDays($policy),
        ]);
    }

    private function findBalance(int $employeeId, int $policyId, CarbonInterface|string $startsAt): ?PtoBalance
    {
        $policy = PtoPolicy::findOrFail($policyId);
        [$periodStart, $periodEnd] = $this->periodFor($policy, $startsAt);

        return PtoBalance::query()
            ->where('employee_id', $employeeId)
            ->where('pto_policy_id', $policyId)
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->first();
    }

    private function periodAllowanceDays(PtoPolicy $policy): float
    {
        $periodsPerYear = match ($policy->accumulation_frequency) {
            'bimonthly' => 24,
            'biweekly' => 26,
            default => 12,
        };

        return round((float) $policy->annual_allowance_days / $periodsPerYear, 2);
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function periodFor(PtoPolicy $policy, CarbonInterface|string $startsAt): array
    {
        $date = Carbon::parse($startsAt)->startOfDay();

        return match ($policy->accumulation_frequency) {
            'bimonthly' => [
                $date->copy()->day <= 15 ? $date->copy()->startOfMonth() : $date->copy()->day(16),
                $date->copy()->day <= 15 ? $date->copy()->day(15) : $date->copy()->endOfMonth(),
            ],
            'biweekly' => $this->biweeklyPeriodFor($date),
            default => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
        };
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function biweeklyPeriodFor(Carbon $date): array
    {
        $anchor = Carbon::create($date->year, 1, 1)->startOfWeek();
        $daysSinceAnchor = (int) $anchor->diffInDays($date);
        $periodStart = $anchor->copy()->addDays(intdiv($daysSinceAnchor, 14) * 14);

        return [$periodStart, $periodStart->copy()->addDays(13)->endOfDay()];
    }
}
