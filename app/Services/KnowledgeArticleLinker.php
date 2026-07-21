<?php

namespace App\Services;

use App\Models\KnowledgeArticle;

class KnowledgeArticleLinker
{
    public function sync(KnowledgeArticle $article): void
    {
        preg_match_all('~/knowledge/(\\d+)~', $article->body_markdown, $matches);

        $targetIds = KnowledgeArticle::query()
            ->whereKey(array_unique(array_map('intval', $matches[1] ?? [])))
            ->whereKeyNot($article->id)
            ->pluck('id')
            ->all();

        $article->outgoingLinks()->sync($targetIds);
    }
}
