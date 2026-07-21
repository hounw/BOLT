<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class PruneOperationalLogs extends Command
{
    protected $signature = 'bolt:prune-operational-logs {--dry-run : Show counts without deleting records}';

    protected $description = 'Prune audit logs and webhook delivery logs beyond configured retention windows.';

    public function handle(): int
    {
        $auditCutoff = now()->subDays((int) config('bolt.retention.audit_days'));
        $webhookCutoff = now()->subDays((int) config('bolt.retention.webhook_delivery_days'));

        $auditQuery = AuditLog::query()->where('occurred_at', '<', $auditCutoff);
        $webhookQuery = WebhookDelivery::query()->where('created_at', '<', $webhookCutoff);

        $auditCount = $auditQuery->count();
        $webhookCount = $webhookQuery->count();

        if ($this->option('dry-run')) {
            $this->components->info("Would prune {$auditCount} audit logs and {$webhookCount} webhook deliveries.");

            return self::SUCCESS;
        }

        $auditQuery->delete();
        $webhookQuery->delete();

        $this->components->info("Pruned {$auditCount} audit logs and {$webhookCount} webhook deliveries.");

        return self::SUCCESS;
    }
}
