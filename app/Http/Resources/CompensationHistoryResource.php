<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompensationHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'effective_date' => $this->effective_date?->toDateString(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'type' => $this->type,
            'notes' => $this->notes,
            'created_by_id' => $this->created_by_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
