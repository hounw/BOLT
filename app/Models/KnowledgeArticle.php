<?php

namespace App\Models;

use App\Enums\KnowledgeArticleStatus;
use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KnowledgeArticle extends Model
{
    use RecordsAudit;

    protected $fillable = [
        'title',
        'slug',
        'body_markdown',
        'excerpt',
        'status',
        'category',
        'category_id',
        'tags',
        'created_by_id',
        'updated_by_id',
        'published_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'status' => KnowledgeArticleStatus::class,
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function categoryRecord(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCategory::class, 'category_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeArticleVersion::class)->orderByDesc('version');
    }

    public function outgoingLinks(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'knowledge_article_links', 'source_article_id', 'target_article_id')->withTimestamps();
    }

    public function incomingLinks(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'knowledge_article_links', 'target_article_id', 'source_article_id')->withTimestamps();
    }

    public function excerptPreview(): string
    {
        if (filled($this->excerpt)) {
            return $this->excerpt;
        }

        return Str::of($this->body_markdown)
            ->replaceMatches('/```.*?```/s', ' ')
            ->replaceMatches('/[#*_>`\[\]()~!-]+/', ' ')
            ->stripTags()
            ->squish()
            ->limit(300, '')
            ->toString();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->can('knowledge.manage')
            ? $query
            : $query->where('status', KnowledgeArticleStatus::Published->value);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);
        $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        if (DB::connection()->getDriverName() === 'mysql' && mb_strlen($term) >= 4) {
            return $query
                ->where(function (Builder $query) use ($needle, $term): void {
                    $query->whereFullText(['title', 'excerpt', 'body_markdown'], $term)
                        ->orWhere('title', 'like', $needle)
                        ->orWhere('slug', 'like', $needle)
                        ->orWhere('category', 'like', $needle)
                        ->orWhere('excerpt', 'like', $needle)
                        ->orWhere('body_markdown', 'like', $needle);
                })
                ->orderByRaw('MATCH(title, excerpt, body_markdown) AGAINST (? IN NATURAL LANGUAGE MODE) DESC', [$term]);
        }

        return $query->where(function (Builder $query) use ($needle): void {
            $query->where('title', 'like', $needle)
                ->orWhere('slug', 'like', $needle)
                ->orWhere('category', 'like', $needle)
                ->orWhere('excerpt', 'like', $needle)
                ->orWhere('body_markdown', 'like', $needle);
        });
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }

    public function scopeFilterCategory(Builder $query, ?string $category): Builder
    {
        return blank($category) ? $query : $query->where('category', $category);
    }

    public function scopeFilterCategoryId(Builder $query, ?int $categoryId): Builder
    {
        return $categoryId ? $query->where('category_id', $categoryId) : $query;
    }

    public function scopeFilterTag(Builder $query, ?string $tag): Builder
    {
        return blank($tag) ? $query : $query->whereJsonContains('tags', $tag);
    }

    public function scopeFilterMissingExcerpt(Builder $query, ?bool $missing): Builder
    {
        return $missing ? $query->where(fn (Builder $query) => $query->whereNull('excerpt')->orWhere('excerpt', '')) : $query;
    }

    public function scopeLinkedFrom(Builder $query, ?int $articleId): Builder
    {
        return $articleId ? $query->whereHas('incomingLinks', fn (Builder $query) => $query->whereKey($articleId)) : $query;
    }

    public function scopeLinkedTo(Builder $query, ?int $articleId): Builder
    {
        return $articleId ? $query->whereHas('outgoingLinks', fn (Builder $query) => $query->whereKey($articleId)) : $query;
    }
}
