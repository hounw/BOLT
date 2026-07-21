<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header title="Assets" description="Track equipment, purchases, warranty dates, and assignments.">
            <x-slot:action>
                @can('create', App\Models\Asset::class)
                    <a href="{{ route('assets.create') }}" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">New asset</a>
                @endcan
            </x-slot:action>
        </x-page-header>

        <x-panel class="mt-6">
            <form method="GET" action="{{ route('assets.index') }}" class="grid gap-4 lg:grid-cols-4">
                <label class="block text-sm font-medium">
                    Search
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <label class="block text-sm font-medium">
                    Status
                    <select name="status" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str($status->value)->title() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Tag
                    <select name="tag" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any tag</option>
                        @foreach ($assetTags as $tag)
                            <option value="{{ $tag }}" @selected(($filters['tag'] ?? '') === $tag)>{{ $tag }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Assigned to
                    <select name="assigned_to" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Anyone</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $employee->id)>{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end justify-end gap-3 lg:col-span-4">
                    <a href="{{ route('assets.index') }}" class="text-sm font-medium text-zinc-600">Clear</a>
                    <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Filter</button>
                </div>
            </form>
        </x-panel>

        <x-panel class="mt-6 overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500"><tr><th class="px-4 py-3 font-medium">Asset</th><th class="px-4 py-3 font-medium">Tags</th><th class="px-4 py-3 font-medium">Assigned to</th><th class="px-4 py-3 font-medium">Status</th></tr></thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($assets as $asset)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-asset-photo :asset="$asset" size="sm" />
                                    <div>
                                        <a class="font-medium hover:underline" href="{{ route('assets.show', $asset) }}">{{ $asset->asset_tag }}</a>
                                        <div class="text-zinc-600">{{ $asset->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-zinc-600">{{ collect($asset->tags ?? [])->implode(', ') ?: '—' }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $asset->currentAssignment?->employee?->full_name ?: '—' }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $asset->status?->value }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">No assets yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
        <div class="mt-4">{{ $assets->links() }}</div>
    </section>
</x-layouts.app>
