<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PtoPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'annual_allowance_days' => $this->annual_allowance_days,
            'accrual_type' => $this->accrual_type?->value,
            'accumulation_frequency' => $this->accumulation_frequency,
            'working_days' => $this->workingDays(),
            'holidays' => $this->holidayDates(),
            'allow_negative_balance' => $this->allow_negative_balance,
            'carryover_days' => $this->carryover_days,
            'approval_strategy' => $this->approval_strategy,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
