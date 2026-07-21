<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PtoBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => trim(($this->employee?->first_name ?? '').' '.($this->employee?->last_name ?? '')),
            'pto_policy_id' => $this->pto_policy_id,
            'policy_name' => $this->policy?->name,
            'available_days' => $this->available_days,
            'pending_days' => $this->pending_days,
            'used_days' => $this->used_days,
            'remaining_days' => number_format(max(0, (float) $this->available_days - (float) $this->pending_days), 2, '.', ''),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
        ];
    }
}
