<?php

namespace App\Services;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;

class KnowledgeTaxonomy
{
    public function categories(): Collection
    {
        return app(KnowledgeCategoryTree::class)->flat();
    }

    public function tags(): Collection
    {
        $used = KnowledgeArticle::query()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->flatMap(fn ($tag): array => explode(',', (string) $tag));

        return $this->normalizeMany(SystemSetting::array(SystemSetting::KNOWLEDGE_TAGS))
            ->merge($this->normalizeMany($used))
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->sort()
            ->values();
    }

    public function managed(string $type): Collection
    {
        if ($type === 'category') {
            return KnowledgeArticle::query()->whereNotNull('category')->pluck('category')->merge(
                KnowledgeCategory::query()->orderBy('name')->pluck('name')
            )->unique(fn (string $value): string => mb_strtolower($value))->values();
        }

        return $this->normalizeMany(SystemSetting::array($this->settingKey($type)));
    }

    public function save(string $type, Collection $values): void
    {
        SystemSetting::putArray(
            $this->settingKey($type),
            $this->normalizeMany($values)->sort()->values()->all(),
        );
    }

    public function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function normalizeMany(iterable $values): Collection
    {
        return collect($values)
            ->map(fn ($value): string => $this->normalize((string) $value))
            ->filter()
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->values();
    }

    private function settingKey(string $type): string
    {
        return $type === 'category'
            ? SystemSetting::KNOWLEDGE_CATEGORIES
            : SystemSetting::KNOWLEDGE_TAGS;
    }
}
