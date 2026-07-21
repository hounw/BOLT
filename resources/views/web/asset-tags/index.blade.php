<x-layouts.app>
    <section class="mx-auto w-full max-w-5xl px-6 py-8">
        <x-page-header title="Asset tags" description="Manage reusable labels for asset inventory filtering and asset forms." />

        @can('assets.manage')
            <x-panel class="mt-6">
                <h2 class="font-semibold">New tag</h2>
                <form method="POST" action="{{ route('asset-tags.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="block min-w-72 flex-1 text-sm font-medium">
                        Name
                        <input name="name" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                    </label>
                    <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Save</button>
                </form>
            </x-panel>
        @endcan

        <x-panel class="mt-6 overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500">
                    <tr><th class="px-4 py-3 font-medium">Tag</th><th class="px-4 py-3 font-medium">Assets</th><th class="px-4 py-3 font-medium">Source</th><th class="px-4 py-3 font-medium"></th></tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($tags as $tag)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $tag['name'] }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $tag['asset_count'] }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $tag['managed'] ? 'Managed' : 'Used on assets' }}</td>
                            <td class="px-4 py-3">
                                @can('assets.manage')
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <form method="POST" action="{{ route('asset-tags.update') }}" class="flex gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="current_name" value="{{ $tag['name'] }}">
                                            <input name="name" value="{{ $tag['name'] }}" required class="w-44 rounded-md border border-zinc-300 px-3 py-2">
                                            <button class="rounded-md border border-zinc-300 px-3 py-2 font-semibold">Rename</button>
                                        </form>
                                        <form method="POST" action="{{ route('asset-tags.destroy') }}">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="name" value="{{ $tag['name'] }}">
                                            <button class="rounded-md border border-red-200 px-3 py-2 font-semibold text-red-700">Delete</button>
                                        </form>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">No asset tags yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
    </section>
</x-layouts.app>
