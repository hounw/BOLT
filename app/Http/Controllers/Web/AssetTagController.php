<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AssetTagController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView($request);

        $managedTags = $this->managedTags();
        $usedTags = $this->usedTags();
        $tags = $managedTags
            ->merge($usedTags)
            ->unique(fn ($tag): string => mb_strtolower((string) $tag))
            ->sort()
            ->values();

        return view('web.asset-tags.index', [
            'tags' => $tags->map(fn (string $tag): array => [
                'name' => $tag,
                'asset_count' => $this->assetCountForTag($tag),
                'managed' => $managedTags->contains(fn ($managedTag): bool => strcasecmp((string) $managedTag, $tag) === 0),
            ]),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);
        $tag = $this->normalizeTag($data['name']);

        $this->saveManagedTags($this->managedTags()->push($tag));
        $auditLogger->log('asset_tag.created', metadata: ['tag' => $tag]);

        return redirect()->route('asset-tags.index')->with('status', 'Asset tag created.');
    }

    public function update(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'current_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $currentName = $this->normalizeTag($data['current_name']);
        $newName = $this->normalizeTag($data['name']);

        if (strcasecmp($currentName, $newName) === 0) {
            $this->saveManagedTags($this->managedTags()->push($newName));

            return redirect()->route('asset-tags.index')->with('status', 'Asset tag saved.');
        }

        $this->saveManagedTags(
            $this->managedTags()
                ->reject(fn ($tag): bool => strcasecmp((string) $tag, $currentName) === 0)
                ->push($newName)
        );

        Asset::query()->where(function ($query) use ($currentName): void {
            $query->whereJsonContains('tags', $currentName)
                ->orWhere('category', $currentName);
        })->each(function (Asset $asset) use ($currentName, $newName): void {
            $tags = collect($asset->tags ?? [])
                ->map(fn ($tag): string => strcasecmp((string) $tag, $currentName) === 0 ? $newName : (string) $tag)
                ->push($asset->category === $currentName ? $newName : null)
                ->filter()
                ->unique(fn ($tag): string => mb_strtolower((string) $tag))
                ->values()
                ->all();

            $asset->forceFill([
                'tags' => $tags,
                'category' => $tags[0] ?? null,
            ])->save();
        });

        $auditLogger->log('asset_tag.renamed', metadata: ['from' => $currentName, 'to' => $newName]);

        return redirect()->route('asset-tags.index')->with('status', 'Asset tag renamed.');
    }

    public function destroy(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);
        $tag = $this->normalizeTag($data['name']);

        $this->saveManagedTags(
            $this->managedTags()->reject(fn ($managedTag): bool => strcasecmp((string) $managedTag, $tag) === 0)
        );

        Asset::query()->where(function ($query) use ($tag): void {
            $query->whereJsonContains('tags', $tag)
                ->orWhere('category', $tag);
        })->each(function (Asset $asset) use ($tag): void {
            $tags = collect($asset->tags ?? [])
                ->reject(fn ($assetTag): bool => strcasecmp((string) $assetTag, $tag) === 0)
                ->values()
                ->all();

            $asset->forceFill([
                'tags' => $tags,
                'category' => $tags[0] ?? null,
            ])->save();
        });

        $auditLogger->log('asset_tag.deleted', metadata: ['tag' => $tag]);

        return redirect()->route('asset-tags.index')->with('status', 'Asset tag deleted.');
    }

    private function authorizeView(Request $request): void
    {
        abort_unless(
            $request->user()?->can(PermissionName::AssetsView->value)
            || $request->user()?->can(PermissionName::AssetsManage->value),
            403
        );
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::AssetsManage->value), 403);
    }

    private function assetCountForTag(string $tag): int
    {
        return Asset::query()
            ->where(fn ($query) => $query
                ->whereJsonContains('tags', $tag)
                ->orWhere('category', $tag))
            ->count();
    }

    private function managedTags(): Collection
    {
        return collect(SystemSetting::array(SystemSetting::ASSET_TAGS))
            ->map(fn ($tag): string => $this->normalizeTag((string) $tag))
            ->filter()
            ->unique(fn ($tag): string => mb_strtolower($tag))
            ->values();
    }

    private function usedTags(): Collection
    {
        $tags = Asset::query()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten();

        $legacyCategories = Asset::query()
            ->whereNotNull('category')
            ->pluck('category');

        return $tags
            ->merge($legacyCategories)
            ->map(fn ($tag): string => $this->normalizeTag((string) $tag))
            ->filter()
            ->unique(fn ($tag): string => mb_strtolower($tag))
            ->values();
    }

    private function saveManagedTags(Collection $tags): void
    {
        SystemSetting::putArray(
            SystemSetting::ASSET_TAGS,
            $tags
                ->map(fn ($tag): string => $this->normalizeTag((string) $tag))
                ->filter()
                ->unique(fn ($tag): string => mb_strtolower($tag))
                ->sort()
                ->values()
                ->all()
        );
    }

    private function normalizeTag(string $tag): string
    {
        return trim(preg_replace('/\s+/', ' ', $tag) ?? '');
    }
}
