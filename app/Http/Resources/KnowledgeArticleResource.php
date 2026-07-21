<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'body_markdown' => $this->body_markdown,
            'excerpt' => $this->excerpt,
            'excerpt_preview' => $this->excerptPreview(),
            'excerpt_missing' => blank($this->excerpt),
            'status' => $this->status?->value,
            'category' => $this->category,
            'category_id' => $this->category_id,
            'category_path' => $this->categoryRecord?->path(),
            'tags' => $this->tags ?? [],
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'published_at' => $this->published_at?->toISOString(),
            'version' => $this->version,
            'attachments_count' => $this->whenCounted('attachments'),
            'versions_count' => $this->whenCounted('versions'),
            'outgoing_links_count' => $this->whenCounted('outgoingLinks'),
            'incoming_links_count' => $this->whenCounted('incomingLinks'),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
