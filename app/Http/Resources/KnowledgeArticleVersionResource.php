<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeArticleVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'knowledge_article_id' => $this->knowledge_article_id,
            'version' => $this->version,
            'title' => $this->title,
            'body_markdown' => $this->body_markdown,
            'excerpt' => $this->excerpt,
            'status' => $this->status?->value,
            'category' => $this->category,
            'category_id' => $this->category_id,
            'tags' => $this->tags ?? [],
            'edited_by_id' => $this->edited_by_id,
            'editor' => $this->whenLoaded('editor', fn (): ?array => $this->editor ? [
                'id' => $this->editor->id,
                'name' => $this->editor->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
