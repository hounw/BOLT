<?php

namespace App\Services;

use App\Enums\KnowledgeArticleStatus;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KnowledgeArticleWriter
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly KnowledgeCategoryResolver $categoryResolver,
        private readonly KnowledgeArticleLinker $linker,
    ) {}

    public function create(array $data, User $user): KnowledgeArticle
    {
        return DB::transaction(function () use ($data, $user): KnowledgeArticle {
            $data = $this->categoryResolver->apply($data);
            $status = $data['status'] ?? KnowledgeArticleStatus::Draft->value;
            $article = KnowledgeArticle::create(array_merge($data, [
                'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['title']),
                'status' => $status,
                'created_by_id' => $user->id,
                'updated_by_id' => $user->id,
                'published_at' => $status === KnowledgeArticleStatus::Published->value ? now() : null,
                'version' => 1,
            ]));

            $this->snapshot($article, $user);
            $this->linker->sync($article);

            return $article;
        });
    }

    public function update(KnowledgeArticle $article, array $data, User $user, ?int $restoredFromVersion = null): KnowledgeArticle
    {
        return DB::transaction(function () use ($article, $data, $user, $restoredFromVersion): KnowledgeArticle {
            $data = $this->categoryResolver->apply($data);
            $oldStatus = $article->status;
            $article->update(array_merge($data, [
                'slug' => $this->uniqueSlug($data['slug'] ?? $article->slug, $data['title'], $article),
                'updated_by_id' => $user->id,
                'version' => $article->version + 1,
                'published_at' => ($data['status'] ?? null) === KnowledgeArticleStatus::Published->value
                    ? ($article->published_at ?? now())
                    : $article->published_at,
            ]));

            $this->snapshot($article, $user);
            $this->linker->sync($article);

            if ($oldStatus !== $article->status) {
                $event = match ($article->status) {
                    KnowledgeArticleStatus::Published => 'knowledge_article.published',
                    KnowledgeArticleStatus::Archived => 'knowledge_article.archived',
                    default => 'knowledge_article.drafted',
                };
                $this->auditLogger->log($event, $article, $user, ['status' => $oldStatus?->value], ['status' => $article->status?->value]);
            }

            if ($restoredFromVersion !== null) {
                $this->auditLogger->log('knowledge_article.version_restored', $article, $user, metadata: [
                    'restored_from_version' => $restoredFromVersion,
                    'saved_as_version' => $article->version,
                ]);
            }

            return $article;
        });
    }

    private function snapshot(KnowledgeArticle $article, User $user): KnowledgeArticleVersion
    {
        return KnowledgeArticleVersion::create([
            'knowledge_article_id' => $article->id,
            'version' => $article->version,
            'title' => $article->title,
            'body_markdown' => $article->body_markdown,
            'excerpt' => $article->excerpt,
            'status' => $article->status,
            'category' => $article->category,
            'category_id' => $article->category_id,
            'tags' => $article->tags,
            'edited_by_id' => $user->id,
        ]);
    }

    private function uniqueSlug(?string $requestedSlug, string $title, ?KnowledgeArticle $ignore = null): string
    {
        $base = Str::slug($requestedSlug ?: $title) ?: 'article';
        $slug = $base;
        $suffix = 2;

        while (KnowledgeArticle::query()
            ->where('slug', $slug)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
