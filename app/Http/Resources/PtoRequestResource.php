<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PtoRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'pto_policy_id' => $this->pto_policy_id,
            'approver_id' => $this->approver_id,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'days' => $this->days,
            'status' => $this->status?->value,
            'reason' => $this->reason,
            'decision_notes' => $this->decision_notes,
            'decided_at' => $this->decided_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
