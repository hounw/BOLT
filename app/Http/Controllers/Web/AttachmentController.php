<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\KnowledgeArticle;
use App\Services\AuditLogger;
use App\Services\WebhookDispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(Request $request, WebhookDispatcher $webhooks): RedirectResponse
    {
        $this->authorize('create', Attachment::class);

        $data = $request->validate([
            'attachable_type' => ['required', 'string', Rule::in(['employees', 'knowledge_articles', 'assets', 'asset_events'])],
            'attachable_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $attachable = $this->resolveAttachable($data['attachable_type'], (int) $data['attachable_id']);
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
            'metadata' => [],
            'uploaded_by_id' => $request->user()->id,
        ]);

        $webhooks->dispatch('attachment.created', ['attachment_id' => $attachment->id]);

        return back()->with('status', 'Attachment uploaded.');
    }

    public function download(Request $request, Attachment $attachment, AuditLogger $auditLogger): StreamedResponse
    {
        $this->authorize('view', $attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        $auditLogger->log('attachment.downloaded', $attachment, $request->user());

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    private function resolveAttachable(string $type, int $id): Model
    {
        return match ($type) {
            'employees' => Employee::findOrFail($id),
            'knowledge_articles' => KnowledgeArticle::findOrFail($id),
            'assets' => Asset::findOrFail($id),
            'asset_events' => AssetEvent::findOrFail($id),
        };
    }
}
