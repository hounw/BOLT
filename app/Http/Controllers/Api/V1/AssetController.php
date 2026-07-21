<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssetAssignmentRequest;
use App\Http\Requests\Api\AssetEventRequest;
use App\Http\Requests\Api\AssetRequest;
use App\Http\Resources\AssetEventResource;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\SystemSetting;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Asset::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
            'tag' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'assigned_to' => ['nullable', 'integer', 'exists:employees,id'],
        ]);
        $selectedTag = $filters['tag'] ?? $filters['category'] ?? null;

        return AssetResource::collection(
            Asset::query()
                ->with('currentAssignment.employee')
                ->search($filters['q'] ?? null)
                ->filterStatus($filters['status'] ?? null)
                ->filterTag($selectedTag)
                ->assignedTo($filters['assigned_to'] ?? null)
                ->latest()
                ->paginate()
                ->withQueryString()
        );
    }

    public function store(AssetRequest $request, WebhookDispatcher $webhooks): AssetResource
    {
        $this->authorize('create', Asset::class);

        $asset = Asset::create($this->assetPayload($request) + [
            'status' => $request->input('status', AssetStatus::Available->value),
        ]);

        $webhooks->dispatch('asset.created', ['asset_id' => $asset->id]);

        return new AssetResource($asset->load('currentAssignment.employee'));
    }

    public function show(Asset $asset): AssetResource
    {
        $this->authorize('view', $asset);

        return new AssetResource($asset->load('currentAssignment.employee'));
    }

    public function update(AssetRequest $request, Asset $asset, WebhookDispatcher $webhooks): AssetResource
    {
        $this->authorize('update', $asset);

        $asset->update($this->assetPayload($request, $asset));
        $webhooks->dispatch('asset.updated', ['asset_id' => $asset->id]);

        return new AssetResource($asset->load('currentAssignment.employee'));
    }

    private function assetPayload(AssetRequest $request, ?Asset $asset = null): array
    {
        $data = $request->validated();
        $hasTagInput = array_key_exists('tags', $data) || array_key_exists('category', $data);
        if ($hasTagInput || ! $asset?->exists) {
            $tags = $this->parseTags($data['tags'] ?? $data['category'] ?? null);
            $data['tags'] = $tags;
            $data['category'] = $tags[0] ?? null;
        } else {
            unset($data['tags'], $data['category']);
        }
        $data['currency'] = SystemSetting::mainCurrency();

        if (! $asset?->exists && blank($data['asset_tag'] ?? null)) {
            $data['asset_tag'] = $this->generateAssetTag();
        } elseif ($asset?->exists && blank($data['asset_tag'] ?? null)) {
            unset($data['asset_tag']);
        }

        return $data;
    }

    private function parseTags(array|string|null $value): array
    {
        return collect(is_array($value) ? $value : explode(',', (string) $value))
            ->map(fn ($tag): string => trim((string) $tag))
            ->filter()
            ->unique(fn ($tag): string => mb_strtolower($tag))
            ->values()
            ->all();
    }

    private function generateAssetTag(): string
    {
        do {
            $tag = 'AST-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (Asset::query()->where('asset_tag', $tag)->exists());

        return $tag;
    }

    public function assign(AssetAssignmentRequest $request, Asset $asset, WebhookDispatcher $webhooks): AssetResource
    {
        $this->authorize('assign', $asset);

        $currentAssignment = $asset->currentAssignment()->first();
        $asset->assignments()->whereNull('returned_at')->update(['returned_at' => now()]);
        $asset->assignments()->create(collect($request->validated())->only(['employee_id', 'assigned_at', 'notes'])->all() + [
            'assigned_at' => $request->input('assigned_at', now()),
            'assigned_by_id' => $request->user()->id,
        ]);
        $asset->update(['status' => AssetStatus::Assigned]);
        $this->recordAssetEvent($request, $asset, [
            'type' => 'assigned',
            'occurred_at' => $request->input('assigned_at', now()),
            'from_employee_id' => $currentAssignment?->employee_id,
            'employee_id' => $request->integer('employee_id'),
            'condition' => $request->input('condition'),
            'notes' => $request->input('notes'),
            'metadata' => [
                'assignment' => $currentAssignment ? 'transfer' : 'initial',
            ],
        ]);
        $webhooks->dispatch('asset.assigned', ['asset_id' => $asset->id, 'employee_id' => $request->integer('employee_id')]);

        return new AssetResource($asset->fresh()->load('currentAssignment.employee'));
    }

    public function return(Request $request, Asset $asset, WebhookDispatcher $webhooks): AssetResource
    {
        $this->authorize('assign', $asset);

        $data = $request->validate([
            'condition' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $currentAssignment = $asset->currentAssignment()->first();
        $asset->assignments()->whereNull('returned_at')->update(['returned_at' => now()]);
        $asset->update(['status' => AssetStatus::Available]);
        $this->recordAssetEvent($request, $asset, [
            'type' => 'returned',
            'from_employee_id' => $currentAssignment?->employee_id,
            'condition' => $data['condition'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $webhooks->dispatch('asset.returned', ['asset_id' => $asset->id]);

        return new AssetResource($asset->fresh()->load('currentAssignment.employee'));
    }

    public function history(Asset $asset): AnonymousResourceCollection
    {
        $this->authorize('view', $asset);

        return AssetEventResource::collection(
            $asset->events()
                ->with(['actor', 'fromEmployee', 'employee', 'attachments.uploader'])
                ->latest('occurred_at')
                ->latest()
                ->paginate()
        );
    }

    public function storeHistory(AssetEventRequest $request, Asset $asset): AssetEventResource
    {
        $this->authorize('create', AssetEvent::class);
        $this->authorize('view', $asset);

        $event = $this->recordAssetEvent($request, $asset, $request->validated());

        return new AssetEventResource($event->load(['actor', 'fromEmployee', 'employee', 'attachments.uploader']));
    }

    private function recordAssetEvent(Request $request, Asset $asset, array $data): AssetEvent
    {
        return $asset->events()->create([
            'type' => $data['type'],
            'occurred_at' => $data['occurred_at'] ?? now(),
            'actor_id' => $request->user()->id,
            'from_employee_id' => $data['from_employee_id'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,
            'condition' => $data['condition'] ?? null,
            'notes' => $data['notes'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }
}
