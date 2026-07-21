<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeArticleSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'excerpt_preview' => $this->excerptPreview(),
            'excerpt_missing' => blank($this->excerpt),
            'category_id' => $this->category_id,
            'category' => $this->category,
            'tags' => $this->tags ?? [],
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
