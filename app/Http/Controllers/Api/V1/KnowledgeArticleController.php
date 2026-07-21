<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\KnowledgeArticleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\KnowledgeArticleRequest;
use App\Http\Resources\KnowledgeArticleResource;
use App\Http\Resources\KnowledgeArticleSummaryResource;
use App\Http\Resources\KnowledgeArticleVersionResource;
use App\Models\KnowledgeArticle;
use App\Services\AuditLogger;
use App\Services\KnowledgeArticleWriter;
use App\Services\MarkdownImporter;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class KnowledgeArticleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', KnowledgeArticle::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(KnowledgeArticleStatus::class)],
            'category' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'tag' => ['nullable', 'string', 'max:80'],
            'missing_excerpt' => ['nullable', 'boolean'],
            'linked_from' => ['nullable', 'integer', 'exists:knowledge_articles,id'],
            'linked_to' => ['nullable', 'integer', 'exists:knowledge_articles,id'],
        ]);

        return KnowledgeArticleResource::collection(
            KnowledgeArticle::query()
                ->visibleTo($request->user())
                ->with('categoryRecord')
                ->withCount(['attachments', 'versions', 'outgoingLinks', 'incomingLinks'])
                ->search($filters['q'] ?? null)
                ->filterStatus($filters['status'] ?? null)
                ->filterCategory($filters['category'] ?? null)
                ->filterCategoryId(isset($filters['category_id']) ? (int) $filters['category_id'] : null)
                ->filterTag($filters['tag'] ?? null)
                ->filterMissingExcerpt(isset($filters['missing_excerpt']) ? (bool) $filters['missing_excerpt'] : null)
                ->linkedFrom(isset($filters['linked_from']) ? (int) $filters['linked_from'] : null)
                ->linkedTo(isset($filters['linked_to']) ? (int) $filters['linked_to'] : null)
                ->latest('updated_at')
                ->paginate()
                ->withQueryString()
        );
    }

    public function store(KnowledgeArticleRequest $request, KnowledgeArticleWriter $writer, WebhookDispatcher $webhooks): KnowledgeArticleResource
    {
        $this->authorize('create', KnowledgeArticle::class);

        $article = $writer->create($request->validated(), $request->user());
        $webhooks->dispatch('knowledge_article.created', ['knowledge_article_id' => $article->id]);

        return new KnowledgeArticleResource($article->load(['attachments', 'categoryRecord'])->loadCount(['attachments', 'versions', 'outgoingLinks', 'incomingLinks']));
    }

    public function import(
        Request $request,
        MarkdownImporter $importer,
        KnowledgeArticleWriter $writer,
        AuditLogger $auditLogger,
        WebhookDispatcher $webhooks,
    ): KnowledgeArticleResource {
        $this->authorize('create', KnowledgeArticle::class);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.MarkdownImporter::MAX_KILOBYTES],
            'title' => ['nullable', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:220'],
            'status' => ['nullable', Rule::enum(KnowledgeArticleStatus::class)],
            'category' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:80'],
        ]);
        $import = $importer->read($request->file('file'));
        $article = $writer->create([
            'title' => $data['title'] ?? $import['suggested_title'],
            'slug' => $data['slug'] ?? null,
            'body_markdown' => $import['body_markdown'],
            'status' => $data['status'] ?? KnowledgeArticleStatus::Draft->value,
            'category' => $data['category'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'tags' => $data['tags'] ?? [],
        ], $request->user());
        $attachment = $importer->attach($article, $request->file('file'), $request->user());

        $auditLogger->log('knowledge_article.imported', $article, $request->user(), metadata: [
            'attachment_id' => $attachment->id,
            'filename' => $attachment->original_name,
        ]);
        $webhooks->dispatch('knowledge_article.created', ['knowledge_article_id' => $article->id]);
        $webhooks->dispatch('attachment.created', ['attachment_id' => $attachment->id]);

        return new KnowledgeArticleResource($article->load(['attachments', 'categoryRecord'])->loadCount(['attachments', 'versions', 'outgoingLinks', 'incomingLinks']));
    }

    public function show(KnowledgeArticle $knowledgeArticle): KnowledgeArticleResource
    {
        $this->authorize('view', $knowledgeArticle);

        return new KnowledgeArticleResource($knowledgeArticle->load(['attachments', 'categoryRecord'])->loadCount(['attachments', 'versions', 'outgoingLinks', 'incomingLinks']));
    }

    public function update(
        KnowledgeArticleRequest $request,
        KnowledgeArticle $knowledgeArticle,
        KnowledgeArticleWriter $writer,
        WebhookDispatcher $webhooks,
    ): KnowledgeArticleResource {
        $this->authorize('update', $knowledgeArticle);

        $writer->update($knowledgeArticle, $request->validated(), $request->user());
        $webhooks->dispatch('knowledge_article.updated', ['knowledge_article_id' => $knowledgeArticle->id]);

        return new KnowledgeArticleResource($knowledgeArticle->load(['attachments', 'categoryRecord'])->loadCount(['attachments', 'versions', 'outgoingLinks', 'incomingLinks']));
    }

    public function versions(KnowledgeArticle $knowledgeArticle): AnonymousResourceCollection
    {
        $this->authorize('view', $knowledgeArticle);

        return KnowledgeArticleVersionResource::collection(
            $knowledgeArticle->versions()->with('editor')->paginate()
        );
    }

    public function links(Request $request, KnowledgeArticle $knowledgeArticle): AnonymousResourceCollection
    {
        $this->authorize('view', $knowledgeArticle);
        $filters = $request->validate(['direction' => ['nullable', Rule::in(['outgoing', 'incoming'])]]);
        $relation = ($filters['direction'] ?? 'outgoing') === 'incoming'
            ? $knowledgeArticle->incomingLinks()
            : $knowledgeArticle->outgoingLinks();

        return KnowledgeArticleSummaryResource::collection(
            $relation->visibleTo($request->user())->orderBy('title')->paginate()->withQueryString()
        );
    }
}
