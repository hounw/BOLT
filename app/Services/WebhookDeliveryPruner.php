<?php

namespace App\Services;

use App\Models\WebhookDelivery;

class WebhookDeliveryPruner
{
    public function pruneToLimit(int $limit, bool $dryRun = false): int
    {
        $limit = max(0, $limit);
        $total = WebhookDelivery::query()->count();
        $excess = max(0, $total - $limit);

        if ($excess === 0 || $dryRun) {
            return $excess;
        }

        $ids = WebhookDelivery::query()
            ->oldest('created_at')
            ->oldest('id')
            ->limit($excess)
            ->pluck('id');

        WebhookDelivery::query()->whereKey($ids)->delete();

        return $ids->count();
    }
}
