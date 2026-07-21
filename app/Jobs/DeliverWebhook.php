<?php

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Services\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public WebhookDelivery $delivery) {}

    public function handle(AuditLogger $auditLogger): void
    {
        $delivery = $this->delivery->fresh('endpoint');
        $endpoint = $delivery->endpoint;
        $payload = [
            'id' => $delivery->id,
            'event' => $delivery->event,
            'occurred_at' => now()->toISOString(),
            'data' => $delivery->payload,
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret);

        try {
            $response = Http::timeout(config('bolt.webhooks.timeout_seconds'))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'BOLT-Webhooks/1.0',
                    'X-BOLT-Event' => $delivery->event,
                    'X-BOLT-Delivery' => (string) $delivery->id,
                    'X-BOLT-Timestamp' => $timestamp,
                    'X-BOLT-Signature' => 'sha256='.$signature,
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $success = $response->successful();

            $delivery->forceFill([
                'status' => $success ? WebhookDeliveryStatus::Delivered : WebhookDeliveryStatus::Failed,
                'attempts' => $delivery->attempts + 1,
                'response_status' => $response->status(),
                'response_body' => str($response->body())->limit(4000)->toString(),
                'error' => $success ? null : 'HTTP '.$response->status(),
                'delivered_at' => $success ? now() : null,
                'next_attempt_at' => $success ? null : $this->nextAttemptAt($delivery->attempts + 1),
            ])->save();

            $endpoint->forceFill([
                'last_delivery_at' => now(),
                'failure_count' => $success ? 0 : $endpoint->failure_count + 1,
                'is_active' => $success || $endpoint->failure_count + 1 < config('bolt.webhooks.max_attempts'),
            ])->save();
        } catch (Throwable $e) {
            $attempts = $delivery->attempts + 1;
            $failureCount = $endpoint->failure_count + 1;

            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Failed,
                'attempts' => $attempts,
                'error' => str($e->getMessage())->limit(1000)->toString(),
                'next_attempt_at' => $this->nextAttemptAt($attempts),
            ])->save();

            $endpoint->forceFill([
                'last_delivery_at' => now(),
                'failure_count' => $failureCount,
                'is_active' => $failureCount < config('bolt.webhooks.max_attempts'),
            ])->save();
        }

        $auditLogger->log('webhook.delivery_attempted', $delivery, metadata: [
            'endpoint_id' => $endpoint->id,
            'event' => $delivery->event,
            'status' => $delivery->status->value,
        ]);
    }

    private function nextAttemptAt(int $attempts): ?Carbon
    {
        if ($attempts >= config('bolt.webhooks.max_attempts')) {
            return null;
        }

        return now()->addMinutes(2 ** max(0, $attempts - 1));
    }
}
