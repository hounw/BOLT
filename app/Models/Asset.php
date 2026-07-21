<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Asset extends Model
{
    use RecordsAudit;

    protected $fillable = [
        'asset_tag',
        'name',
        'photo_path',
        'category',
        'tags',
        'serial_number',
        'status',
        'purchase_date',
        'purchase_cost',
        'currency',
        'vendor',
        'warranty_expires_on',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'purchase_date' => 'date',
            'warranty_expires_on' => 'date',
            'purchase_cost' => 'decimal:2',
            'metadata' => 'array',
            'tags' => 'array',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_at')->latestOfMany('assigned_at');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $query->where(function (Builder $query) use ($needle): void {
            $query->where('asset_tag', 'like', $needle)
                ->orWhere('name', 'like', $needle)
                ->orWhere('category', 'like', $needle)
                ->orWhere('tags', 'like', $needle)
                ->orWhere('serial_number', 'like', $needle)
                ->orWhere('vendor', 'like', $needle);
        });
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }

    public function scopeFilterCategory(Builder $query, ?string $category): Builder
    {
        return $this->scopeFilterTag($query, $category);
    }

    public function scopeFilterTag(Builder $query, ?string $tag): Builder
    {
        return blank($tag)
            ? $query
            : $query->where(fn (Builder $query): Builder => $query
                ->whereJsonContains('tags', $tag)
                ->orWhere('category', $tag));
    }

    public function scopeAssignedTo(Builder $query, null|int|string $employeeId): Builder
    {
        if (blank($employeeId)) {
            return $query;
        }

        return $query->whereHas('assignments', function (Builder $query) use ($employeeId): void {
            $query->where('employee_id', (int) $employeeId)
                ->whereNull('returned_at');
        });
    }
}
