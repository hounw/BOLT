<?php

namespace App\Models;

use App\Enums\PtoAccrualType;
use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PtoPolicy extends Model
{
    use RecordsAudit;

    public const ACCUMULATION_FREQUENCIES = [
        'monthly' => 'Monthly',
        'bimonthly' => 'Twice monthly',
        'biweekly' => 'Every other week',
    ];

    public const WEEKDAYS = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];

    protected $fillable = [
        'name',
        'annual_allowance_days',
        'accrual_type',
        'accumulation_frequency',
        'working_days',
        'holidays',
        'allow_negative_balance',
        'carryover_days',
        'approval_strategy',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'annual_allowance_days' => 'decimal:2',
            'carryover_days' => 'decimal:2',
            'working_days' => 'array',
            'holidays' => 'array',
            'allow_negative_balance' => 'boolean',
            'accrual_type' => PtoAccrualType::class,
            'is_default' => 'boolean',
        ];
    }

    public function accumulationFrequencyLabel(): string
    {
        return self::ACCUMULATION_FREQUENCIES[$this->accumulation_frequency] ?? str($this->accumulation_frequency)->replace('_', ' ')->title()->toString();
    }

    public function workingDays(): array
    {
        return $this->working_days ?: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    }

    public function holidayDates(): array
    {
        return $this->holidays ?: [];
    }

    public function balances(): HasMany
    {
        return $this->hasMany(PtoBalance::class);
    }
}
