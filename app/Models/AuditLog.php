<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'event',
        'auditable_type',
        'auditable_id',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForEvent(Builder $query, ?string $event): Builder
    {
        return blank($event) ? $query : $query->where('event', $event);
    }

    public function scopeForActor(Builder $query, null|int|string $actorId): Builder
    {
        return blank($actorId) ? $query : $query->where('actor_id', (int) $actorId);
    }

    public function scopeForAuditableType(Builder $query, ?string $type): Builder
    {
        return blank($type) ? $query : $query->where('auditable_type', $type);
    }

    public function scopeForAuditableId(Builder $query, null|int|string $id): Builder
    {
        return blank($id) ? $query : $query->where('auditable_id', (string) $id);
    }

    public function scopeOccurredOnOrAfter(Builder $query, ?string $date): Builder
    {
        return blank($date) ? $query : $query->whereDate('occurred_at', '>=', $date);
    }

    public function scopeOccurredOnOrBefore(Builder $query, ?string $date): Builder
    {
        return blank($date) ? $query : $query->whereDate('occurred_at', '<=', $date);
    }
}
