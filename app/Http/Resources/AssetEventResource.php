<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'type' => $this->type,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'actor' => $this->actor ? [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ] : null,
            'from_employee' => $this->fromEmployee ? [
                'id' => $this->fromEmployee->id,
                'name' => $this->fromEmployee->full_name,
            ] : null,
            'employee' => $this->employee ? [
                'id' => $this->employee->id,
                'name' => $this->employee->full_name,
            ] : null,
            'condition' => $this->condition,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
