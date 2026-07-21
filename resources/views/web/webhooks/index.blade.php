<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header title="Webhooks" description="Manage signed outbound event delivery.">
            <x-slot:action>
                @can('create', App\Models\WebhookEndpoint::class)
                    <a href="{{ route('webhooks.create') }}" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">New endpoint</a>
                @endcan
            </x-slot:action>
        </x-page-header>

        <x-panel class="mt-6">
            <form method="GET" action="{{ route('webhooks.index') }}" class="grid gap-4 md:grid-cols-3">
                <label class="block text-sm font-medium">
                    Search
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <label class="block text-sm font-medium">
                    Status
                    <select name="is_active" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any status</option>
                        <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                        <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Disabled</option>
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Event
                    <select name="event" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any event</option>
                        @foreach ($events as $event => $description)
                            <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $event }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end justify-end gap-3 md:col-span-3">
                    <a href="{{ route('webhooks.index') }}" class="text-sm font-medium text-zinc-600">Clear</a>
                    <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Filter</button>
                </div>
            </form>
        </x-panel>

        <div class="mt-6 grid gap-4">
            @forelse ($endpoints as $endpoint)
                <x-panel>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <a href="{{ route('webhooks.show', $endpoint) }}" class="font-semibold hover:underline">{{ $endpoint->name }}</a>
                            <p class="mt-1 break-all text-sm text-zinc-500">{{ $endpoint->url }}</p>
                            <p class="mt-1 text-sm text-zinc-500">{{ $endpoint->is_active ? 'Active' : 'Disabled' }} · {{ $endpoint->deliveries_count }} deliveries</p>
                        </div>
                        @can('update', $endpoint)<a href="{{ route('webhooks.edit', $endpoint) }}" class="text-sm font-medium text-zinc-600">Edit</a>@endcan
                    </div>
                </x-panel>
            @empty
                <x-panel><p class="text-sm text-zinc-500">No webhook endpoints.</p></x-panel>
            @endforelse
        </div>
        <div class="mt-4">{{ $endpoints->links() }}</div>
    </section>
</x-layouts.app>
