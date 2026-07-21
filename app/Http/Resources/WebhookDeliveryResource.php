<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'webhook_endpoint_id' => $this->webhook_endpoint_id,
            'event' => $this->event,
            'payload' => $this->payload,
            'status' => $this->status?->value,
            'attempts' => $this->attempts,
            'response_status' => $this->response_status,
            'response_body' => $this->response_body,
            'error' => $this->error,
            'next_attempt_at' => $this->next_attempt_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
