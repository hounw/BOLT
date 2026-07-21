<?php

namespace App\Http\Controllers\Web;

use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\SystemSetting;
use App\Services\WebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Asset::class);

        $filters = request()->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
            'tag' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'assigned_to' => ['nullable', 'integer', 'exists:employees,id'],
        ]);
        $selectedTag = $filters['tag'] ?? $filters['category'] ?? null;

        return view('web.assets.index', [
            'assets' => Asset::with(['assignments.employee', 'currentAssignment.employee'])
                ->search($filters['q'] ?? null)
                ->filterStatus($filters['status'] ?? null)
                ->filterTag($selectedTag)
                ->assignedTo($filters['assigned_to'] ?? null)
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'assetTags' => $this->assetTags(),
            'employees' => Employee::query()->orderBy('first_name')->orderBy('last_name')->get(),
            'filters' => $filters + ['tag' => $selectedTag],
            'statuses' => AssetStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Asset::class);

        return view('web.assets.form', [
            'asset' => new Asset,
            'assetTags' => $this->assetTags(),
            'mainCurrency' => SystemSetting::mainCurrency(),
            'mainCurrencySymbol' => SystemSetting::mainCurrencySymbol(),
            'statuses' => AssetStatus::cases(),
        ]);
    }

    public function store(Request $request, WebhookDispatcher $webhooks): RedirectResponse
    {
        $this->authorize('create', Asset::class);

        $asset = Asset::create($this->validated($request));
        $this->syncPhotos($request, $asset);
        $webhooks->dispatch('asset.created', ['asset_id' => $asset->id]);

        return redirect()->route('assets.show', $asset)->with('status', 'Asset created.');
    }

    public function show(Asset $asset): View
    {
        $this->authorize('view', $asset);

        return view('web.assets.show', [
            'asset' => $asset->load([
                'assignments.employee',
                'attachments.uploader',
                'currentAssignment.employee',
                'events.actor',
                'events.fromEmployee',
                'events.employee',
                'events.attachments.uploader',
            ]),
            'employees' => Employee::orderBy('first_name')->get(),
            'eventTypes' => AssetEvent::TYPES,
        ]);
    }

    public function photo(Asset $asset): StreamedResponse
    {
        $this->authorize('view', $asset);

        abort_unless($asset->photo_path && Storage::disk('local')->exists($asset->photo_path), 404);

        return Storage::disk('local')->response($asset->photo_path);
    }

    public function edit(Asset $asset): View
    {
        $this->authorize('update', $asset);

        return view('web.assets.form', [
            'asset' => $asset,
            'assetTags' => $this->assetTags(),
            'mainCurrency' => SystemSetting::mainCurrency(),
            'mainCurrencySymbol' => SystemSetting::mainCurrencySymbol(),
            'statuses' => AssetStatus::cases(),
        ]);
    }

    public function update(Request $request, Asset $asset, WebhookDispatcher $webhooks): RedirectResponse
    {
        $this->authorize('update', $asset);

        $asset->update($this->validated($request, $asset));
        $this->syncPhotos($request, $asset);
        $webhooks->dispatch('asset.updated', ['asset_id' => $asset->id]);

        return redirect()->route('assets.show', $asset)->with('status', 'Asset updated.');
    }

    public function assign(Request $request, Asset $asset, WebhookDispatcher $webhooks): RedirectResponse
    {
        $this->authorize('assign', $asset);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'condition' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'files.*' => ['nullable', 'file', 'max:20480'],
        ]);

        $currentAssignment = $asset->currentAssignment()->with('employee')->first();
        $asset->assignments()->whereNull('returned_at')->update(['returned_at' => now()]);
        $assignmentData = collect($data)->only(['employee_id', 'notes'])->all();
        $asset->assignments()->create($assignmentData + [
            'assigned_at' => now(),
            'assigned_by_id' => $request->user()->id,
        ]);
        $asset->update(['status' => AssetStatus::Assigned]);
        $event = $this->recordAssetEvent($request, $asset, [
            'type' => 'assigned',
            'from_employee_id' => $currentAssignment?->employee_id,
            'employee_id' => (int) $data['employee_id'],
            'condition' => $data['condition'] ?? null,
            'notes' => $data['notes'] ?? null,
            'metadata' => [
                'assignment' => $currentAssignment ? 'transfer' : 'initial',
            ],
        ]);
        $this->storeEventFiles($request, $event);
        $webhooks->dispatch('asset.assigned', ['asset_id' => $asset->id, 'employee_id' => (int) $data['employee_id']]);

        return redirect()->route('assets.show', $asset)->with('status', 'Asset assigned.');
    }

    public function return(Request $request, Asset $asset, WebhookDispatcher $webhooks): RedirectResponse
    {
        $this->authorize('assign', $asset);

        $data = $request->validate([
            'condition' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'files.*' => ['nullable', 'file', 'max:20480'],
        ]);
        $currentAssignment = $asset->currentAssignment()->first();
        $asset->assignments()->whereNull('returned_at')->update(['returned_at' => now()]);
        $asset->update(['status' => AssetStatus::Available]);
        $event = $this->recordAssetEvent($request, $asset, [
            'type' => 'returned',
            'from_employee_id' => $currentAssignment?->employee_id,
            'condition' => $data['condition'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $this->storeEventFiles($request, $event);
        $webhooks->dispatch('asset.returned', ['asset_id' => $asset->id]);

        return redirect()->route('assets.show', $asset)->with('status', 'Asset returned.');
    }

    public function storeEvent(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('create', AssetEvent::class);
        $this->authorize('view', $asset);

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(AssetEvent::TYPES))],
            'occurred_at' => ['nullable', 'date'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'condition' => ['nullable', 'string', 'max:120'],
            'notes' => ['required', 'string', 'max:2000'],
            'files.*' => ['nullable', 'file', 'max:20480'],
        ]);

        $event = $this->recordAssetEvent($request, $asset, $data + [
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);
        $this->storeEventFiles($request, $event);

        return redirect()->route('assets.show', $asset)->with('status', 'Asset history entry added.');
    }

    private function validated(Request $request, ?Asset $asset = null): array
    {
        $data = $request->validate([
            'asset_tag' => ['nullable', 'string', 'max:80', Rule::unique('assets')->ignore($asset)],
            'name' => ['required', 'string', 'max:180'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:4096'],
            'remove_photo' => ['sometimes', 'boolean'],
            'tags' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:180', Rule::unique('assets')->ignore($asset)],
            'status' => ['required', Rule::enum(AssetStatus::class)],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'vendor' => ['nullable', 'string', 'max:180'],
            'warranty_expires_on' => ['nullable', 'date'],
        ]);

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

        unset($data['photos'], $data['remove_photo']);

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

    private function assetTags()
    {
        $tags = Asset::query()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten();

        $legacyCategories = Asset::query()
            ->whereNotNull('category')
            ->pluck('category');

        $managedTags = collect(SystemSetting::array(SystemSetting::ASSET_TAGS));

        return $managedTags
            ->merge($tags)
            ->merge($legacyCategories)
            ->filter()
            ->unique(fn ($tag): string => mb_strtolower((string) $tag))
            ->sort()
            ->values();
    }

    private function syncPhotos(Request $request, Asset $asset): void
    {
        if ($request->boolean('remove_photo') && $asset->photo_path) {
            $asset->forceFill(['photo_path' => null])->save();
        }

        if (! $request->hasFile('photos')) {
            return;
        }

        $photos = $request->file('photos', []);

        foreach ($photos as $index => $photo) {
            if (! $photo) {
                continue;
            }

            $path = $photo->store('asset-photos', 'local');

            if ($index === 0) {
                $asset->forceFill(['photo_path' => $path])->save();
            }

            Attachment::create([
                'attachable_type' => $asset->getMorphClass(),
                'attachable_id' => $asset->getKey(),
                'disk' => 'local',
                'path' => $path,
                'original_name' => $photo->getClientOriginalName(),
                'mime_type' => $photo->getMimeType(),
                'size' => $photo->getSize(),
                'metadata' => ['source' => 'asset_photo'],
                'uploaded_by_id' => $request->user()->id,
            ]);
        }
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

    private function storeEventFiles(Request $request, AssetEvent $event): void
    {
        foreach ($request->file('files', []) as $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store('attachments/'.now()->format('Y/m'), 'local');

            Attachment::create([
                'attachable_type' => $event->getMorphClass(),
                'attachable_id' => $event->getKey(),
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'metadata' => ['source' => 'asset_event'],
                'uploaded_by_id' => $request->user()->id,
            ]);
        }
    }
}
