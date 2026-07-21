<?php

namespace App\Http\Controllers\Web;

use App\Enums\KnowledgeArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleVersion;
use App\Services\AuditLogger;
use App\Services\KnowledgeArticleWriter;
use App\Services\KnowledgeTaxonomy;
use App\Services\MarkdownImporter;
use App\Services\MarkdownRenderer;
use App\Services\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KnowledgeArticleController extends Controller
{
    public function __construct(private readonly KnowledgeTaxonomy $taxonomy) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', KnowledgeArticle::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(KnowledgeArticleStatus::class)],
            'category' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'tag' => ['nullable', 'string', 'max:80'],
            'missing_excerpt' => ['nullable', 'boolean'],
        ]);

        $query = KnowledgeArticle::query()->visibleTo($request->user());

        return view('web.knowledge.index', [
            'articles' => $query
                ->with(['creator', 'updater', 'categoryRecord'])
                ->withCount(['attachments', 'versions', 'outgoingLinks', 'incomingLinks'])
                ->search($filters['q'] ?? null)
                ->filterStatus($filters['status'] ?? null)
                ->filterCategory($filters['category'] ?? null)
                ->filterCategoryId(isset($filters['category_id']) ? (int) $filters['category_id'] : null)
                ->filterTag($filters['tag'] ?? null)
                ->filterMissingExcerpt(isset($filters['missing_excerpt']) ? (bool) $filters['missing_excerpt'] : null)
                ->latest('updated_at')
                ->paginate(20)
                ->withQueryString(),
            'filters' => $filters,
            'categories' => $this->taxonomy->categories(),
            'tags' => (clone $query)
                ->whereNotNull('tags')
                ->pluck('tags')
                ->flatten()
                ->flatMap(fn ($tag): array => explode(',', (string) $tag))
                ->map(fn ($tag): string => $this->taxonomy->normalize((string) $tag))
                ->filter()
                ->unique(fn (string $tag): string => mb_strtolower($tag))
                ->sort()
                ->values(),
            'statuses' => KnowledgeArticleStatus::cases(),
            'canManage' => $request->user()->can('knowledge.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', KnowledgeArticle::class);

        return view('web.knowledge.form', $this->formData(new KnowledgeArticle));
    }

    public function store(
        Request $request,
        KnowledgeArticleWriter $writer,
        MarkdownImporter $importer,
        AuditLogger $auditLogger,
        WebhookDispatcher $webhooks,
    ): RedirectResponse {
        $this->authorize('create', KnowledgeArticle::class);

        $source = $this->prepareImport($request, $importer);
        $data = $this->validated($request);
        $article = $writer->create($data, $request->user());

        if ($source) {
            $attachment = $importer->attach($article, $source, $request->user());
            $auditLogger->log('knowledge_article.imported', $article, $request->user(), metadata: [
                'attachment_id' => $attachment->id,
                'filename' => $attachment->original_name,
            ]);
            $webhooks->dispatch('attachment.created', ['attachment_id' => $attachment->id]);
        }

        $webhooks->dispatch('knowledge_article.created', ['knowledge_article_id' => $article->id]);

        return redirect()->route('knowledge.show', $article)->with('status', 'Article created.');
    }

    public function show(KnowledgeArticle $knowledgeArticle, MarkdownRenderer $renderer): View
    {
        $this->authorize('view', $knowledgeArticle);

        $knowledgeArticle->load([
            'attachments.uploader',
            'creator',
            'updater',
            'categoryRecord.parent',
            'outgoingLinks' => fn ($query) => $query->visibleTo(request()->user())->with('categoryRecord')->orderBy('title'),
            'incomingLinks' => fn ($query) => $query->visibleTo(request()->user())->with('categoryRecord')->orderBy('title'),
        ])->loadCount('versions');

        return view('web.knowledge.show', [
            'article' => $knowledgeArticle,
            'rendered' => $renderer->render($knowledgeArticle->body_markdown),
        ]);
    }

    public function edit(KnowledgeArticle $knowledgeArticle): View
    {
        $this->authorize('update', $knowledgeArticle);

        return view('web.knowledge.form', $this->formData($knowledgeArticle));
    }

    public function update(
        Request $request,
        KnowledgeArticle $knowledgeArticle,
        KnowledgeArticleWriter $writer,
        WebhookDispatcher $webhooks,
    ): RedirectResponse {
        $this->authorize('update', $knowledgeArticle);

        $data = $this->validated($request);
        $restoredFrom = $request->integer('restored_from_version') ?: null;
        $writer->update($knowledgeArticle, $data, $request->user(), $restoredFrom);
        $webhooks->dispatch('knowledge_article.updated', ['knowledge_article_id' => $knowledgeArticle->id]);

        return redirect()->route('knowledge.show', $knowledgeArticle)->with('status', 'Article updated.');
    }

    public function preview(Request $request, MarkdownRenderer $renderer): JsonResponse
    {
        $this->authorize('create', KnowledgeArticle::class);
        $data = $request->validate(['body_markdown' => ['nullable', 'string', 'max:2097152']]);

        return response()->json(['data' => $renderer->render($data['body_markdown'] ?? '')]);
    }

    public function linkSearch(Request $request): JsonResponse
    {
        $this->authorize('create', KnowledgeArticle::class);
        $filters = $request->validate([
            'q' => ['required', 'string', 'max:120'],
            'exclude' => ['nullable', 'integer'],
        ]);

        $articles = KnowledgeArticle::query()
            ->with('categoryRecord')
            ->search($filters['q'])
            ->when($filters['exclude'] ?? null, fn ($query, $id) => $query->whereKeyNot($id))
            ->limit(10)
            ->get()
            ->map(fn (KnowledgeArticle $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'status' => $article->status?->value,
                'category' => $article->categoryRecord?->path() ?? $article->category,
                'markdown' => '['.$article->title.'](/knowledge/'.$article->id.')',
            ]);

        return response()->json(['data' => $articles]);
    }

    public function versions(KnowledgeArticle $knowledgeArticle): View
    {
        $this->authorize('update', $knowledgeArticle);

        return view('web.knowledge.versions', [
            'article' => $knowledgeArticle,
            'versions' => $knowledgeArticle->versions()->with('editor')->paginate(20),
        ]);
    }

    public function restore(KnowledgeArticle $knowledgeArticle, KnowledgeArticleVersion $knowledgeArticleVersion): View
    {
        $this->authorize('update', $knowledgeArticle);
        abort_unless($knowledgeArticleVersion->knowledge_article_id === $knowledgeArticle->id, 404);

        return view('web.knowledge.form', $this->formData($knowledgeArticle, $knowledgeArticleVersion));
    }

    private function prepareImport(Request $request, MarkdownImporter $importer): ?UploadedFile
    {
        $request->validate([
            'source_markdown' => ['nullable', 'file', 'max:'.MarkdownImporter::MAX_KILOBYTES],
        ]);

        $source = $request->file('source_markdown');
        if (! $source) {
            return null;
        }

        $import = $importer->read($source);
        $request->merge([
            'title' => $request->input('title') ?: $import['suggested_title'],
            'body_markdown' => $request->input('body_markdown') ?: $import['body_markdown'],
        ]);

        return $source;
    }

    private function validated(Request $request): array
    {
        if (is_string($request->input('tags'))) {
            $request->merge(['tags' => explode(',', $request->input('tags'))]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:220'],
            'body_markdown' => ['required', 'string', 'max:2097152'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'status' => ['required', Rule::enum(KnowledgeArticleStatus::class)],
            'category' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:80'],
        ]);

        $data['tags'] = collect($data['tags'] ?? [])
            ->flatMap(fn ($tag): array => explode(',', (string) $tag))
            ->map(fn ($tag): string => $this->taxonomy->normalize((string) $tag))
            ->filter()
            ->unique(fn (string $tag): string => strtolower($tag))
            ->values()
            ->all();

        return $data;
    }

    private function formData(KnowledgeArticle $article, ?KnowledgeArticleVersion $restoreVersion = null): array
    {
        return [
            'article' => $article,
            'restoreVersion' => $restoreVersion,
            'statuses' => KnowledgeArticleStatus::cases(),
            'categories' => $this->taxonomy->categories(),
            'tags' => $this->taxonomy->tags(),
            'linkSearchUrl' => route('knowledge.link-search'),
        ];
    }
}
