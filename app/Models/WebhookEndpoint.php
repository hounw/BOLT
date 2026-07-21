<?php

namespace App\Models;

use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use RecordsAudit;

    protected $fillable = ['name', 'url', 'secret', 'events', 'is_active', 'last_delivery_at', 'failure_count'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'last_delivery_at' => 'datetime',
            'failure_count' => 'integer',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function auditRedactedAttributes(): array
    {
        return ['secret'];
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $query->where(function (Builder $query) use ($needle): void {
            $query->where('name', 'like', $needle)
                ->orWhere('url', 'like', $needle);
        });
    }

    public function scopeFilterActive(Builder $query, null|bool|string $active): Builder
    {
        if ($active === null || $active === '') {
            return $query;
        }

        return $query->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
    }

    public function scopeSubscribedTo(Builder $query, ?string $event): Builder
    {
        return blank($event) ? $query : $query->whereJsonContains('events', $event);
    }
}
