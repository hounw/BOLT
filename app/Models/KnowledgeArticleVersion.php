<?php

namespace App\Models;

use App\Enums\KnowledgeArticleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeArticleVersion extends Model
{
    protected $fillable = [
        'knowledge_article_id',
        'version',
        'title',
        'body_markdown',
        'excerpt',
        'status',
        'category',
        'category_id',
        'tags',
        'edited_by_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => KnowledgeArticleStatus::class,
            'tags' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'knowledge_article_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_id');
    }

    public function categoryRecord(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCategory::class, 'category_id');
    }
}
