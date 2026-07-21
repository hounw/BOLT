<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Services\KnowledgeCategoryTree;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeCategoryController extends Controller
{
    public function index(Request $request, KnowledgeCategoryTree $tree): View
    {
        $this->authorize('viewAny', KnowledgeCategory::class);

        return view('web.knowledge-categories.index', [
            'categories' => $tree->flat(),
        ]);
    }

    public function show(Request $request, KnowledgeCategory $knowledgeCategory): View
    {
        $this->authorize('view', $knowledgeCategory);

        $articles = KnowledgeArticle::query()
            ->visibleTo($request->user())
            ->where('category_id', $knowledgeCategory->id)
            ->with('categoryRecord')
            ->latest('updated_at')
            ->paginate(20);

        $children = $knowledgeCategory->children()
            ->withCount(['articles' => fn ($query) => $query->visibleTo($request->user())])
            ->with(['articles' => fn ($query) => $query->visibleTo($request->user())->latest('updated_at')->limit(3)])
            ->get();

        return view('web.knowledge-categories.show', [
            'category' => $knowledgeCategory->load('parent'),
            'children' => $children,
            'articles' => $articles,
        ]);
    }
}
