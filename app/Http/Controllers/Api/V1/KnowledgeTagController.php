<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use App\Services\KnowledgeTaxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeTagController extends Controller
{
    public function index(Request $request, KnowledgeTaxonomy $taxonomy): JsonResponse
    {
        $this->authorize('viewAny', KnowledgeArticle::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $tags = $taxonomy->tags()->when($filters['q'] ?? null, fn ($items, $q) => $items->filter(fn (string $tag) => str_contains(mb_strtolower($tag), mb_strtolower($q))))->values();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 25);

        return response()->json([
            'data' => $tags->forPage($page, $perPage)->values()->map(fn (string $tag): array => ['name' => $tag]),
            'meta' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $tags->count(), 'last_page' => max(1, (int) ceil($tags->count() / $perPage))],
        ]);
    }
}
