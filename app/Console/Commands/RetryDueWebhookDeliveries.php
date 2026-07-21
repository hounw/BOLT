<?php

namespace App\Console\Commands;

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class RetryDueWebhookDeliveries extends Command
{
    protected $signature = 'bolt:retry-webhooks {--limit=100 : Maximum due deliveries to dispatch}';

    protected $description = 'Dispatch due failed webhook deliveries for retry.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $maxAttempts = config('bolt.webhooks.max_attempts');

        $deliveries = WebhookDelivery::query()
            ->with('endpoint')
            ->where('status', WebhookDeliveryStatus::Failed)
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', now())
            ->where('attempts', '<', $maxAttempts)
            ->whereHas('endpoint', fn ($endpoint) => $endpoint->where('is_active', true))
            ->oldest('next_attempt_at')
            ->limit($limit)
            ->get();

        $deliveries->each(function (WebhookDelivery $delivery): void {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Pending,
            ])->save();

            DeliverWebhook::dispatch($delivery);
        });

        $this->components->info("Dispatched {$deliveries->count()} webhook retry deliveries.");

        return self::SUCCESS;
    }
}
