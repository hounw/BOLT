<x-layouts.app>
    <section class="mx-auto w-full max-w-6xl px-6 py-8">
        <x-page-header :title="$endpoint->name" :description="$endpoint->url">
            <x-slot:action>
                @can('update', $endpoint)
                    <div class="flex gap-2">
                        @if ($endpoint->is_active)
                            <form method="POST" action="{{ route('webhooks.test', $endpoint) }}">@csrf<button class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">Send test</button></form>
                        @endif
                        <a href="{{ route('webhooks.edit', $endpoint) }}" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Edit</a>
                    </div>
                @endcan
            </x-slot:action>
        </x-page-header>

        <div class="mt-6 grid gap-4 md:grid-cols-4">
            <x-panel>
                <p class="text-sm font-medium text-zinc-500">Status</p>
                <p class="mt-2 text-xl font-semibold">{{ $endpoint->is_active ? 'Active' : 'Disabled' }}</p>
            </x-panel>
            <x-panel>
                <p class="text-sm font-medium text-zinc-500">Failure count</p>
                <p class="mt-2 text-xl font-semibold">{{ $endpoint->failure_count }}</p>
            </x-panel>
            <x-panel>
                <p class="text-sm font-medium text-zinc-500">Last delivery</p>
                <p class="mt-2 text-sm font-semibold">{{ $endpoint->last_delivery_at?->diffForHumans() ?: 'Never' }}</p>
            </x-panel>
            <x-panel>
                <p class="text-sm font-medium text-zinc-500">Retry eligible</p>
                <p class="mt-2 text-xl font-semibold">{{ $endpoint->is_active ? $retryEligibleCount : 0 }}</p>
            </x-panel>
        </div>

        <x-panel class="mt-6">
            <h2 class="font-semibold">Subscribed events</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($endpoint->events ?? [] as $event)
                    <span class="rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1 text-xs font-medium">{{ $event }}</span>
                @endforeach
            </div>
        </x-panel>

        @unless ($endpoint->is_active)
            <div class="mt-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">This endpoint is disabled. Reactivate it before sending test or replay deliveries.</div>
        @endunless

        <x-panel class="mt-6">
            <form method="GET" action="{{ route('webhooks.show', $endpoint) }}" class="grid gap-4 lg:grid-cols-4">
                <label class="block text-sm font-medium">
                    Status
                    <select name="status" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any status</option>
                        @foreach ($deliveryStatuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str($status->value)->title() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Event
                    <select name="event" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any event</option>
                        @foreach ($deliveryEvents as $event)
                            <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $event }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    From
                    <input name="created_from" type="date" value="{{ $filters['created_from'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <label class="block text-sm font-medium">
                    Until
                    <input name="created_until" type="date" value="{{ $filters['created_until'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <div class="flex items-end justify-end gap-3 lg:col-span-4">
                    <a href="{{ route('webhooks.show', $endpoint) }}" class="text-sm font-medium text-zinc-600">Clear</a>
                    <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Filter</button>
                </div>
            </form>
        </x-panel>

        <x-panel class="mt-6 overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500"><tr><th class="px-4 py-3 font-medium">Event</th><th class="px-4 py-3 font-medium">Status</th><th class="px-4 py-3 font-medium">Attempts</th><th class="px-4 py-3 font-medium">Response</th><th class="px-4 py-3 font-medium">Created</th><th class="px-4 py-3 font-medium"></th></tr></thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($deliveries as $delivery)
                        <tr>
                            <td class="px-4 py-3 font-medium"><a href="{{ route('webhook-deliveries.show', $delivery) }}" class="hover:underline">{{ $delivery->event }}</a></td>
                            <td class="px-4 py-3 text-zinc-600">{{ $delivery->status?->value }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $delivery->attempts }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $delivery->response_status ?: ($delivery->error ?: '—') }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $delivery->created_at?->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('webhook-deliveries.show', $delivery) }}" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold">Details</a>
                                    @can('update', $endpoint)
                                        @if ($endpoint->is_active)
                                            <form method="POST" action="{{ route('webhook-deliveries.replay', $delivery) }}">@csrf<button class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold">Replay</button></form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No deliveries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
        <div class="mt-4">{{ $deliveries->links() }}</div>
    </section>
</x-layouts.app>
