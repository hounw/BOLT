<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Services\WebhookDeliveryPruner;
use Illuminate\Console\Command;

class PruneWebhookDeliveries extends Command
{
    protected $signature = 'bolt:prune-webhook-deliveries {--dry-run : Show count without deleting records}';

    protected $description = 'Prune oldest webhook deliveries beyond the configured total history limit.';

    public function handle(WebhookDeliveryPruner $pruner): int
    {
        $limit = SystemSetting::integer(SystemSetting::WEBHOOK_DELIVERY_HISTORY_LIMIT, 10000);
        $count = $pruner->pruneToLimit($limit, (bool) $this->option('dry-run'));

        $prefix = $this->option('dry-run') ? 'Would prune' : 'Pruned';
        $this->components->info("{$prefix} {$count} webhook deliveries beyond the {$limit} record limit.");

        return self::SUCCESS;
    }
}
