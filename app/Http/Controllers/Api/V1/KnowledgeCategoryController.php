<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\KnowledgeArticleSummaryResource;
use App\Http\Resources\KnowledgeCategoryResource;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Services\AuditLogger;
use App\Services\KnowledgeCategoryTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KnowledgeCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', KnowledgeCategory::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
        ]);

        return KnowledgeCategoryResource::collection(
            KnowledgeCategory::query()
                ->with('parent')
                ->withCount([
                    'children',
                    'articles' => fn ($query) => $query->visibleTo($request->user()),
                ])
                ->search($filters['q'] ?? null)
                ->when(array_key_exists('parent_id', $filters), fn ($query) => $query->where('parent_id', $filters['parent_id']))
                ->orderBy('name')
                ->paginate()
                ->withQueryString()
        );
    }

    public function show(Request $request, KnowledgeCategory $knowledgeCategory): KnowledgeCategoryResource
    {
        $this->authorize('view', $knowledgeCategory);

        return new KnowledgeCategoryResource($knowledgeCategory
            ->load('parent')
            ->loadCount([
                'children',
                'articles' => fn ($query) => $query->visibleTo($request->user()),
            ]));
    }

    public function store(Request $request, AuditLogger $auditLogger): KnowledgeCategoryResource
    {
        $this->authorize('create', KnowledgeCategory::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
        ]);
        $category = KnowledgeCategory::create([
            'name' => trim($data['name']),
            'slug' => $this->uniqueSlug($data['name']),
            'parent_id' => $data['parent_id'] ?? null,
        ]);
        $auditLogger->log('knowledge_category.created', $category, $request->user(), newValues: $category->only(['name', 'parent_id']));

        return new KnowledgeCategoryResource($category->load('parent')->loadCount(['children', 'articles']));
    }

    public function update(Request $request, KnowledgeCategory $knowledgeCategory, KnowledgeCategoryTree $tree, AuditLogger $auditLogger): KnowledgeCategoryResource
    {
        $this->authorize('update', $knowledgeCategory);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:knowledge_categories,id'],
        ]);
        $parentId = array_key_exists('parent_id', $data) ? ($data['parent_id'] ? (int) $data['parent_id'] : null) : $knowledgeCategory->parent_id;
        if ($tree->wouldCreateCycle($knowledgeCategory, $parentId)) {
            throw ValidationException::withMessages(['parent_id' => 'A category cannot be placed inside itself or one of its descendants.']);
        }

        $old = $knowledgeCategory->only(['name', 'parent_id']);
        $name = trim($data['name'] ?? $knowledgeCategory->name);
        $knowledgeCategory->update([
            'name' => $name,
            'slug' => $this->uniqueSlug($name, $knowledgeCategory),
            'parent_id' => $parentId,
        ]);
        $knowledgeCategory->articles()->update(['category' => $name]);
        $auditLogger->log('knowledge_category.updated', $knowledgeCategory, $request->user(), $old, $knowledgeCategory->only(['name', 'parent_id']));

        return new KnowledgeCategoryResource($knowledgeCategory->load('parent')->loadCount(['children', 'articles']));
    }

    public function destroy(Request $request, KnowledgeCategory $knowledgeCategory, AuditLogger $auditLogger): Response
    {
        $this->authorize('delete', $knowledgeCategory);
        if ($knowledgeCategory->children()->exists() || $knowledgeCategory->articles()->exists()) {
            throw ValidationException::withMessages(['category' => 'Move child categories and reassign articles before deleting this category.']);
        }

        $metadata = $knowledgeCategory->only(['id', 'name']);
        $knowledgeCategory->delete();
        $auditLogger->log('knowledge_category.deleted', actor: $request->user(), metadata: $metadata);

        return response()->noContent();
    }

    public function digest(Request $request, KnowledgeCategory $knowledgeCategory): AnonymousResourceCollection
    {
        $this->authorize('view', $knowledgeCategory);

        return KnowledgeArticleSummaryResource::collection(
            KnowledgeArticle::query()
                ->visibleTo($request->user())
                ->where('category_id', $knowledgeCategory->id)
                ->latest('updated_at')
                ->paginate()
                ->withQueryString()
        );
    }

    public function categoryIndex(Request $request, KnowledgeCategory $knowledgeCategory, KnowledgeCategoryTree $tree): AnonymousResourceCollection
    {
        $this->authorize('view', $knowledgeCategory);
        $ids = $tree->descendantIds($knowledgeCategory);

        return KnowledgeCategoryResource::collection(
            KnowledgeCategory::query()
                ->whereKey($ids)
                ->with('parent')
                ->withCount([
                    'children',
                    'articles' => fn ($query) => $query->visibleTo($request->user()),
                ])
                ->with(['articles' => fn ($query) => $query->visibleTo($request->user())->latest('updated_at')->limit(3)])
                ->orderBy('name')
                ->paginate()
                ->withQueryString()
        );
    }

    private function uniqueSlug(string $name, ?KnowledgeCategory $ignore = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;
        while (KnowledgeCategory::query()->where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
