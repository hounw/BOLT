<?php

namespace App\Models;

use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use RecordsAudit;

    protected $fillable = ['parent_id', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with(['childrenRecursive', 'employees' => fn ($query) => $query->orderBy('first_name')->orderBy('last_name')]);
    }

    public function pathName(): string
    {
        if (! $this->parent) {
            return $this->name;
        }

        return $this->parent->pathName().' / '.$this->name;
    }

    public function wouldCreateCycle(?int $parentId): bool
    {
        if (! $this->exists || blank($parentId)) {
            return false;
        }

        if ($this->id === $parentId) {
            return true;
        }

        $current = self::find($parentId);

        while ($current) {
            if ($current->parent_id === $this->id) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $query->where(function (Builder $query) use ($needle): void {
            $query->where('name', 'like', $needle)
                ->orWhere('description', 'like', $needle);
        });
    }
}
