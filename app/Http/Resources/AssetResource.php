<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_tag' => $this->asset_tag,
            'name' => $this->name,
            'photo' => [
                'has_photo' => filled($this->photo_path),
            ],
            'category' => $this->category,
            'tags' => $this->tags ?? [],
            'serial_number' => $this->serial_number,
            'status' => $this->status?->value,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_cost' => $this->purchase_cost,
            'currency' => $this->currency,
            'vendor' => $this->vendor,
            'warranty_expires_on' => $this->warranty_expires_on?->toDateString(),
            'current_holder' => $this->currentAssignment?->employee ? [
                'id' => $this->currentAssignment->employee->id,
                'name' => $this->currentAssignment->employee->full_name,
            ] : null,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
