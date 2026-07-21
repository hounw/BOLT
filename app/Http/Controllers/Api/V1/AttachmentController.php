<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AttachmentStoreRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\KnowledgeArticle;
use App\Services\AuditLogger;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(AttachmentStoreRequest $request, WebhookDispatcher $webhooks): AttachmentResource
    {
        $this->authorize('create', Attachment::class);

        $attachable = $this->resolveAttachable($request->string('attachable_type')->toString(), $request->integer('attachable_id'));
        $this->authorize('view', $attachable);

        $file = $request->file('file');
        $path = $file->store('attachments/'.now()->format('Y/m'), 'local');

        $attachment = Attachment::create([
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'metadata' => $request->input('metadata', []),
            'uploaded_by_id' => $request->user()->id,
        ]);

        $webhooks->dispatch('attachment.created', ['attachment_id' => $attachment->id]);

        return new AttachmentResource($attachment);
    }

    public function show(Attachment $attachment): AttachmentResource
    {
        $this->authorize('view', $attachment);

        return new AttachmentResource($attachment);
    }

    public function download(Request $request, Attachment $attachment, AuditLogger $auditLogger): StreamedResponse
    {
        $this->authorize('view', $attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        $auditLogger->log('attachment.downloaded', $attachment, $request->user());

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    private function resolveAttachable(string $type, int $id): Employee|KnowledgeArticle|Asset|AssetEvent
    {
        return match ($type) {
            'employees' => Employee::findOrFail($id),
            'knowledge_articles' => KnowledgeArticle::findOrFail($id),
            'assets' => Asset::findOrFail($id),
            'asset_events' => AssetEvent::findOrFail($id),
        };
    }
}
