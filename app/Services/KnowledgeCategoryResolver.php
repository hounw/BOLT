<?php

namespace App\Services;

use App\Models\KnowledgeCategory;
use Illuminate\Support\Str;

class KnowledgeCategoryResolver
{
    public function resolve(?int $categoryId, ?string $legacyName): ?KnowledgeCategory
    {
        if ($categoryId) {
            return KnowledgeCategory::findOrFail($categoryId);
        }

        $name = trim(preg_replace('/\s+/', ' ', (string) $legacyName) ?? '');
        if ($name === '') {
            return null;
        }

        $existing = KnowledgeCategory::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($existing) {
            return $existing;
        }

        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;
        while (KnowledgeCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return KnowledgeCategory::create(['name' => $name, 'slug' => $slug]);
    }

    public function apply(array $data): array
    {
        $category = $this->resolve(
            isset($data['category_id']) ? (int) $data['category_id'] : null,
            $data['category'] ?? null,
        );

        $data['category_id'] = $category?->id;
        $data['category'] = $category?->name;

        return $data;
    }
}
