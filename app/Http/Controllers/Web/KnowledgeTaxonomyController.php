<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Services\AuditLogger;
use App\Services\KnowledgeCategoryTree;
use App\Services\KnowledgeTaxonomy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KnowledgeTaxonomyController extends Controller
{
    public function __construct(
        private readonly KnowledgeTaxonomy $taxonomy,
        private readonly KnowledgeCategoryTree $categoryTree,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeManage($request);

        return view('web.knowledge-taxonomy.index', [
            'categories' => $this->categoryTree->flat(),
            'tags' => $this->taxonomy->tags()->map(fn (string $name): array => [
                'name' => $name,
                'article_count' => $this->tagCount($name),
                'managed' => $this->contains($this->taxonomy->managed('tag'), $name),
            ]),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validated($request);
        $name = $this->taxonomy->normalize($data['name']);

        if ($data['type'] === 'category') {
            $category = KnowledgeCategory::create([
                'name' => $name,
                'slug' => $this->uniqueCategorySlug($name),
                'parent_id' => $data['parent_id'] ?? null,
            ]);
            $auditLogger->log('knowledge_category.created', $category, newValues: $category->only(['name', 'parent_id']));

            return back()->with('status', 'Category created.');
        }

        $this->taxonomy->save($data['type'], $this->taxonomy->managed($data['type'])->push($name));
        $auditLogger->log('knowledge_taxonomy.created', metadata: ['type' => $data['type'], 'name' => $name]);

        return back()->with('status', str($data['type'])->title().' created.');
    }

    public function update(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validated($request, true);
        $name = $this->taxonomy->normalize($data['name']);

        if ($data['type'] === 'category') {
            $category = KnowledgeCategory::findOrFail($data['category_id']);
            $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
            if ($this->categoryTree->wouldCreateCycle($category, $parentId)) {
                throw ValidationException::withMessages(['parent_id' => 'A category cannot be placed inside itself or one of its descendants.']);
            }

            $old = $category->only(['name', 'parent_id']);
            $category->update([
                'name' => $name,
                'slug' => $this->uniqueCategorySlug($name, $category),
                'parent_id' => $parentId,
            ]);
            $category->articles()->update(['category' => $name]);
            $auditLogger->log('knowledge_category.updated', $category, oldValues: $old, newValues: $category->only(['name', 'parent_id']));

            return back()->with('status', 'Category saved.');
        }

        $current = $this->taxonomy->normalize($data['current_name']);

        $this->taxonomy->save(
            $data['type'],
            $this->taxonomy->managed($data['type'])
                ->reject(fn (string $value): bool => strcasecmp($value, $current) === 0)
                ->push($name),
        );

        $this->updateArticleTags(fn (string $tag): string => strcasecmp($tag, $current) === 0 ? $name : $tag);

        $auditLogger->log('knowledge_taxonomy.renamed', metadata: ['type' => $data['type'], 'from' => $current, 'to' => $name]);

        return back()->with('status', str($data['type'])->title().' renamed.');
    }

    public function destroy(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validated($request, true, false);
        if ($data['type'] === 'category') {
            $category = KnowledgeCategory::findOrFail($data['category_id']);
            if ($category->children()->exists() || $category->articles()->exists()) {
                throw ValidationException::withMessages(['category_id' => 'Move child categories and reassign articles before deleting this category.']);
            }
            $name = $category->name;
            $category->delete();
            $auditLogger->log('knowledge_category.deleted', metadata: ['id' => $category->id, 'name' => $name]);

            return back()->with('status', 'Category deleted.');
        }

        $name = $this->taxonomy->normalize($data['current_name']);

        $this->taxonomy->save(
            $data['type'],
            $this->taxonomy->managed($data['type'])
                ->reject(fn (string $value): bool => strcasecmp($value, $name) === 0),
        );

        $this->updateArticleTags(fn (string $tag): ?string => strcasecmp($tag, $name) === 0 ? null : $tag);

        $auditLogger->log('knowledge_taxonomy.deleted', metadata: ['type' => $data['type'], 'name' => $name]);

        return back()->with('status', str($data['type'])->title().' deleted.');
    }

    private function validated(Request $request, bool $current = false, bool $newName = true): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['category', 'tag'])],
            'current_name' => [$current ? 'required_if:type,tag' : 'nullable', 'string', 'max:120'],
            'category_id' => [$current ? 'required_if:type,category' : 'nullable', 'integer', 'exists:knowledge_categories,id'],
            'parent_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'name' => [$newName ? 'required' : 'nullable', 'string', 'max:120'],
        ]);
    }

    private function updateArticleTags(callable $transform): void
    {
        KnowledgeArticle::query()->whereNotNull('tags')->each(function (KnowledgeArticle $article) use ($transform): void {
            $tags = collect($article->tags ?? [])
                ->flatMap(fn ($tag): array => explode(',', (string) $tag))
                ->map(fn ($tag): string => $this->taxonomy->normalize((string) $tag))
                ->map($transform)
                ->filter()
                ->unique(fn (string $tag): string => mb_strtolower($tag))
                ->values()
                ->all();

            if ($tags !== ($article->tags ?? [])) {
                $article->update(['tags' => $tags]);
            }
        });
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::KnowledgeManage->value), 403);
    }

    private function tagCount(string $name): int
    {
        return KnowledgeArticle::query()
            ->whereNotNull('tags')
            ->get(['tags'])
            ->filter(fn (KnowledgeArticle $article): bool => collect($article->tags ?? [])
                ->flatMap(fn ($tag): array => explode(',', (string) $tag))
                ->map(fn ($tag): string => $this->taxonomy->normalize((string) $tag))
                ->contains(fn (string $tag): bool => strcasecmp($tag, $name) === 0))
            ->count();
    }

    private function contains(iterable $values, string $needle): bool
    {
        return collect($values)->contains(fn (string $value): bool => strcasecmp($value, $needle) === 0);
    }

    private function uniqueCategorySlug(string $name, ?KnowledgeCategory $ignore = null): string
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
