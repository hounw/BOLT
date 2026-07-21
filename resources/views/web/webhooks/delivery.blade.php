<x-layouts.app>
    <section class="mx-auto w-full max-w-6xl px-6 py-8">
        <x-page-header :title="'Webhook delivery #'.$delivery->id" :description="$delivery->endpoint?->name.' - '.$delivery->event">
            <x-slot:action>
                <div class="flex gap-2">
                    <a href="{{ route('webhooks.show', $delivery->endpoint) }}" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">Endpoint</a>
                    @can('update', $delivery->endpoint)
                        @if ($delivery->endpoint?->is_active)
                            <form method="POST" action="{{ route('webhook-deliveries.replay', $delivery) }}">
                                @csrf
                                <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Replay</button>
                            </form>
                        @endif
                    @endcan
                </div>
            </x-slot:action>
        </x-page-header>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <x-panel>
                <p class="text-sm font-medium text-zinc-500">Status</p>
                <p class="mt-2 text-xl font-semibold">{{ str($delivery->status?->value)->title() }}</p>
            </x-panel>
            <x-panel>
                <p class="text-sm font-medium text-zinc-500">Attempts</p>
                <p class="mt-2 text-xl font-semibold">{{ $delivery->attempts }}</p>
            </x-panel>
            <x-panel>
                <p class="text-sm font-medium text-zinc-500">Response</p>
                <p class="mt-2 text-xl font-semibold">{{ $delivery->response_status ?: 'No response' }}</p>
            </x-panel>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-panel>
                <h2 class="font-semibold">Timeline</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div><dt class="text-zinc-500">Created</dt><dd class="font-medium">{{ $delivery->created_at?->toDayDateTimeString() }}</dd></div>
                    <div><dt class="text-zinc-500">Delivered</dt><dd class="font-medium">{{ $delivery->delivered_at?->toDayDateTimeString() ?: 'Not delivered' }}</dd></div>
                    <div><dt class="text-zinc-500">Next retry</dt><dd class="font-medium">{{ $delivery->next_attempt_at?->toDayDateTimeString() ?: 'None scheduled' }}</dd></div>
                    <div><dt class="text-zinc-500">Error</dt><dd class="font-medium">{{ $delivery->error ?: 'None' }}</dd></div>
                </dl>
            </x-panel>

            <x-panel>
                <h2 class="font-semibold">Response body</h2>
                <pre class="mt-4 max-h-80 overflow-auto rounded-md bg-zinc-950 p-4 text-xs text-zinc-50">{{ $delivery->response_body ?: 'No response body recorded.' }}</pre>
            </x-panel>
        </div>

        <x-panel class="mt-6">
            <h2 class="font-semibold">Payload</h2>
            <pre class="mt-4 max-h-96 overflow-auto rounded-md bg-zinc-950 p-4 text-xs text-zinc-50">{{ json_encode($delivery->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </x-panel>
    </section>
</x-layouts.app>
