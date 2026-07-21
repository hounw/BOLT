<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header
            title="Operations dashboard"
            description="A quick read on the BOLT core: people, PTO, knowledge, assets, audit, webhooks, and API docs."
        />

        <div class="mt-6 grid gap-4 md:grid-cols-4">
            @foreach ($counts as $label => $value)
                <x-panel>
                    <p class="text-sm font-medium capitalize text-zinc-500">{{ str_replace('_', ' ', $label) }}</p>
                    <p class="mt-3 text-3xl font-semibold">{{ $value }}</p>
                </x-panel>
            @endforeach
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
            <x-panel>
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Recent audit</h2>
                    <a href="{{ route('audit.index') }}" class="text-sm font-medium text-zinc-600">View all</a>
                </div>
                <div class="mt-4 divide-y divide-zinc-100">
                    @forelse ($recentAudits as $audit)
                        <div class="py-3 text-sm">
                            <p class="font-medium">{{ $audit->event }}</p>
                            <p class="mt-1 text-zinc-500">{{ $audit->occurred_at?->diffForHumans() }} · {{ class_basename($audit->auditable_type ?? 'System') }} #{{ $audit->auditable_id ?? 'n/a' }}</p>
                        </div>
                    @empty
                        <p class="py-6 text-sm text-zinc-500">No audit events yet.</p>
                    @endforelse
                </div>
            </x-panel>

            <x-panel>
                <h2 class="font-semibold">Failed webhooks</h2>
                <div class="mt-4 divide-y divide-zinc-100">
                    @forelse ($failedWebhooks as $delivery)
                        <div class="py-3 text-sm">
                            <p class="font-medium">{{ $delivery->event }}</p>
                            <p class="mt-1 text-zinc-500">{{ $delivery->error ?: 'Delivery failed' }}</p>
                        </div>
                    @empty
                        <p class="py-6 text-sm text-zinc-500">No failed webhook deliveries.</p>
                    @endforelse
                </div>
            </x-panel>
        </div>
    </section>
</x-layouts.app>
