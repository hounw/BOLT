<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'webhook_endpoint_id',
        'event',
        'payload',
        'status',
        'attempts',
        'response_status',
        'response_body',
        'error',
        'next_attempt_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookDeliveryStatus::class,
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function scopeForEvent(Builder $query, ?string $event): Builder
    {
        return blank($event) ? $query : $query->where('event', $event);
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }

    public function scopeCreatedOnOrAfter(Builder $query, ?string $date): Builder
    {
        return blank($date) ? $query : $query->whereDate('created_at', '>=', $date);
    }

    public function scopeCreatedOnOrBefore(Builder $query, ?string $date): Builder
    {
        return blank($date) ? $query : $query->whereDate('created_at', '<=', $date);
    }
}
