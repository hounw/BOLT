<?php

namespace App\Services;

use App\Models\KnowledgeCategory;
use Illuminate\Support\Collection;

class KnowledgeCategoryTree
{
    public function flat(): Collection
    {
        $categories = KnowledgeCategory::query()->withCount('articles')->orderBy('name')->get()->groupBy('parent_id');

        return $this->flatten($categories, null, '', 0);
    }

    public function descendantIds(KnowledgeCategory $category): Collection
    {
        $children = KnowledgeCategory::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = collect();
        $queue = collect([$category->id]);

        while ($queue->isNotEmpty()) {
            $parentId = $queue->shift();
            foreach ($children->get($parentId, collect()) as $child) {
                if (! $ids->contains($child->id)) {
                    $ids->push($child->id);
                    $queue->push($child->id);
                }
            }
        }

        return $ids;
    }

    public function wouldCreateCycle(KnowledgeCategory $category, ?int $parentId): bool
    {
        return $parentId !== null
            && ($parentId === $category->id || $this->descendantIds($category)->contains($parentId));
    }

    private function flatten(Collection $grouped, ?int $parentId, string $prefix, int $depth): Collection
    {
        return $grouped->get($parentId, collect())->flatMap(function (KnowledgeCategory $category) use ($grouped, $prefix, $depth): Collection {
            $path = $prefix === '' ? $category->name : $prefix.' / '.$category->name;

            return collect([[
                'category' => $category,
                'path' => $path,
                'depth' => $depth,
                'article_count' => $category->articles_count,
            ]])->merge($this->flatten($grouped, $category->id, $path, $depth + 1));
        });
    }
}
