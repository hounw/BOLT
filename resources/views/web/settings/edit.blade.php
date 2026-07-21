<x-layouts.app>
    <section class="mx-auto w-full max-w-4xl px-6 py-8">
        <x-page-header title="Settings" description="Operational configuration for this BOLT deployment." />

        <x-panel class="mt-6">
            <form method="POST" action="{{ route('settings.update') }}" class="grid gap-5">
                @csrf
                @method('PUT')

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium">
                        Main currency
                        <input name="main_currency" value="{{ old('main_currency', $mainCurrency) }}" maxlength="3" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 uppercase">
                        <span class="mt-1 block text-xs font-normal text-zinc-500">Used by compensation packages, compensation history, and asset purchase costs. BOLT keeps one currency for the MVP.</span>
                    </label>

                    <label class="block text-sm font-medium">
                        Webhook delivery history limit
                        <input name="webhook_delivery_history_limit" type="number" min="0" max="1000000" value="{{ old('webhook_delivery_history_limit', $webhookDeliveryHistoryLimit) }}" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <span class="mt-1 block text-xs font-normal text-zinc-500">Total webhook delivery records to keep. Oldest records over this cap are pruned. Default: 10000.</span>
                    </label>

                    <label class="block text-sm font-medium">
                        Queue workers
                        <input name="queue_worker_count" type="number" min="1" max="100" value="{{ old('queue_worker_count', $queueWorkerCount) }}" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <span class="mt-1 block text-xs font-normal text-zinc-500">Operational target for deployments. Configure your process manager to run this many `php artisan queue:work` processes.</span>
                    </label>
                </div>

                @if ($errors->any())
                    <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
                @endif

                <div class="flex justify-end">
                    <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Save settings</button>
                </div>
            </form>
        </x-panel>
    </section>
</x-layouts.app>
