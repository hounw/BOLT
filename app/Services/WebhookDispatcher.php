<?php

namespace App\Services;

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WebhookDispatcher
{
    public function dispatch(string $event, array $payload): Collection
    {
        return WebhookEndpoint::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => in_array($event, $endpoint->events ?? [], true) || in_array('*', $endpoint->events ?? [], true))
            ->map(function (WebhookEndpoint $endpoint) use ($event, $payload): WebhookDelivery {
                $delivery = $endpoint->deliveries()->create([
                    'event' => $event,
                    'payload' => $payload,
                    'status' => WebhookDeliveryStatus::Pending,
                    'next_attempt_at' => now(),
                ]);

                DeliverWebhook::dispatch($delivery);

                return $delivery;
            })
            ->values();
    }

    public function replay(WebhookDelivery $delivery): WebhookDelivery
    {
        if (! $delivery->endpoint?->is_active) {
            throw ValidationException::withMessages([
                'webhook_endpoint_id' => 'Reactivate the endpoint before replaying deliveries.',
            ]);
        }

        $replay = $delivery->endpoint->deliveries()->create([
            'event' => $delivery->event,
            'payload' => $delivery->payload,
            'status' => WebhookDeliveryStatus::Pending,
            'next_attempt_at' => now(),
        ]);

        DeliverWebhook::dispatch($replay);

        return $replay;
    }

    public function test(WebhookEndpoint $endpoint): WebhookDelivery
    {
        if (! $endpoint->is_active) {
            throw ValidationException::withMessages([
                'webhook_endpoint_id' => 'Reactivate the endpoint before sending a test delivery.',
            ]);
        }

        $delivery = $endpoint->deliveries()->create([
            'event' => 'webhook.test',
            'payload' => [
                'webhook_endpoint_id' => $endpoint->id,
                'message' => 'BOLT webhook test delivery.',
            ],
            'status' => WebhookDeliveryStatus::Pending,
            'next_attempt_at' => now(),
        ]);

        DeliverWebhook::dispatch($delivery);

        return $delivery;
    }
}
