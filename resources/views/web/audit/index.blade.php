<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header title="Audit log" description="Read-only operational history for sensitive and core events." />

        <x-panel class="mt-6">
            <form method="GET" action="{{ route('audit.index') }}" class="grid gap-4 lg:grid-cols-3">
                <label class="block text-sm font-medium">
                    Event
                    <select name="event" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any event</option>
                        @foreach ($events as $event)
                            <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $event }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Actor
                    <select name="actor_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Anyone</option>
                        @foreach ($actorOptions as $actor)
                            <option value="{{ $actor->id }}" @selected((string) ($filters['actor_id'] ?? '') === (string) $actor->id)>{{ $actor->name }} · {{ $actor->email }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Target type
                    <select name="auditable_type" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any target</option>
                        @foreach ($auditableTypes as $type)
                            <option value="{{ $type }}" @selected(($filters['auditable_type'] ?? '') === $type)>{{ class_basename($type) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Target ID
                    <input name="auditable_id" type="number" min="1" value="{{ $filters['auditable_id'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <label class="block text-sm font-medium">
                    From
                    <input name="occurred_from" type="date" value="{{ $filters['occurred_from'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <label class="block text-sm font-medium">
                    Until
                    <input name="occurred_until" type="date" value="{{ $filters['occurred_until'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <div class="flex items-end justify-end gap-3 lg:col-span-3">
                    <a href="{{ route('audit.index') }}" class="text-sm font-medium text-zinc-600">Clear</a>
                    <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Filter</button>
                </div>
            </form>
        </x-panel>

        <x-panel class="mt-6 overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500"><tr><th class="px-4 py-3 font-medium">Time</th><th class="px-4 py-3 font-medium">Event</th><th class="px-4 py-3 font-medium">Actor</th><th class="px-4 py-3 font-medium">Target</th></tr></thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($auditLogs as $audit)
                        <tr><td class="px-4 py-3 text-zinc-600">{{ $audit->occurred_at?->toDayDateTimeString() }}</td><td class="px-4 py-3 font-medium">{{ $audit->event }}</td><td class="px-4 py-3 text-zinc-600">#{{ $audit->actor_id ?: 'system' }}</td><td class="px-4 py-3 text-zinc-600">{{ class_basename($audit->auditable_type ?? 'System') }} #{{ $audit->auditable_id ?: '—' }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">No audit events.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
        <div class="mt-4">{{ $auditLogs->links() }}</div>
    </section>
</x-layouts.app>
