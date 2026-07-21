<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompensationPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'amount_basis' => $this->amount_basis,
            'amount_basis_label' => $this->amountBasisLabel(),
            'payment_frequency' => $this->payment_frequency,
            'payment_frequency_label' => $this->paymentFrequencyLabel(),
            'type' => $this->type,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
