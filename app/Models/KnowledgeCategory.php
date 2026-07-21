<?php

namespace App\Models;

use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class KnowledgeCategory extends Model
{
    use RecordsAudit;

    protected $fillable = ['name', 'slug', 'parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgeArticle::class, 'category_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return blank($term)
            ? $query
            : $query->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%');
    }

    public function pathNames(): Collection
    {
        $path = collect([$this->name]);
        $current = $this->parent;
        $visited = [$this->id];

        while ($current && ! in_array($current->id, $visited, true)) {
            $visited[] = $current->id;
            $path->prepend($current->name);
            $current = $current->parent;
        }

        return $path;
    }

    public function path(): string
    {
        return $this->pathNames()->implode(' / ');
    }
}
