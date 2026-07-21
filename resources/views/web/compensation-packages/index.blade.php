<x-layouts.app>
    <section class="mx-auto w-full max-w-6xl px-6 py-8">
        <x-page-header title="Compensation packages" description="Manage reusable compensation defaults for employee onboarding." />

        <x-panel class="mt-6">
            <form method="GET" action="{{ route('compensation-packages.index') }}" class="flex flex-wrap items-end gap-3">
                <label class="block min-w-72 flex-1 text-sm font-medium">
                    Search
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <a href="{{ route('compensation-packages.index') }}" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">Clear</a>
                <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Filter</button>
            </form>
        </x-panel>

        @can('hr.compensation.manage')
            <x-panel class="mt-6">
                <h2 class="font-semibold">New package</h2>
                <p class="mt-1 text-sm text-zinc-500">Currency is set once in Platform > Settings: {{ $mainCurrency }}.</p>
                <form method="POST" action="{{ route('compensation-packages.store') }}" class="mt-4 grid gap-4 lg:grid-cols-5">
                    @csrf
                    <label class="block text-sm font-medium">Name<input name="name" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Amount<x-money-input name="amount" :currency="$mainCurrency" :symbol="$mainCurrencySymbol" required /></label>
                    <label class="block text-sm font-medium">Amount basis<select name="amount_basis" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">@foreach (\App\Models\CompensationPackage::AMOUNT_BASES as $value => $label)<option value="{{ $value }}" @selected(old('amount_basis', 'annual') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="block text-sm font-medium">Pay frequency<select name="payment_frequency" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">@foreach (\App\Models\CompensationPackage::PAYMENT_FREQUENCIES as $value => $label)<option value="{{ $value }}" @selected(old('payment_frequency', 'monthly') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="block text-sm font-medium">Type<input name="type" value="salary" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="flex items-end gap-2 pb-2 text-sm font-medium"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                    <label class="block text-sm font-medium lg:col-span-4">Notes<input name="notes" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <div class="flex items-end justify-end"><button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Save</button></div>
                </form>
            </x-panel>
        @endcan

        <x-panel class="mt-6 overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500">
                    <tr><th class="px-4 py-3 font-medium">Name</th><th class="px-4 py-3 font-medium">Amount</th><th class="px-4 py-3 font-medium">Basis</th><th class="px-4 py-3 font-medium">Pay frequency</th><th class="px-4 py-3 font-medium">Type</th><th class="px-4 py-3 font-medium">Notes</th><th class="px-4 py-3 font-medium">Status</th><th class="px-4 py-3 font-medium"></th></tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($packages as $package)
                        <tr>
                            <td colspan="8" class="px-4 py-3">
                                <form method="POST" action="{{ route('compensation-packages.update', $package) }}" class="grid gap-3 xl:grid-cols-[1fr_9rem_9rem_10rem_8rem_1fr_7rem_5rem] xl:items-center">
                                    @csrf
                                    @method('PUT')
                                    <input name="name" value="{{ old('name', $package->name) }}" @cannot('hr.compensation.manage') disabled @endcannot class="w-full rounded-md border border-zinc-300 px-3 py-2">
                                    <x-money-input name="amount" :value="old('amount', $package->amount)" :currency="$mainCurrency" :symbol="$mainCurrencySymbol" :disabled="auth()->user()->cannot('hr.compensation.manage')" />
                                    <select name="amount_basis" @cannot('hr.compensation.manage') disabled @endcannot class="w-full rounded-md border border-zinc-300 px-3 py-2">@foreach (\App\Models\CompensationPackage::AMOUNT_BASES as $value => $label)<option value="{{ $value }}" @selected(old('amount_basis', $package->amount_basis) === $value)>{{ $label }}</option>@endforeach</select>
                                    <select name="payment_frequency" @cannot('hr.compensation.manage') disabled @endcannot class="w-full rounded-md border border-zinc-300 px-3 py-2">@foreach (\App\Models\CompensationPackage::PAYMENT_FREQUENCIES as $value => $label)<option value="{{ $value }}" @selected(old('payment_frequency', $package->payment_frequency) === $value)>{{ $label }}</option>@endforeach</select>
                                    <input name="type" value="{{ old('type', $package->type) }}" @cannot('hr.compensation.manage') disabled @endcannot class="w-full rounded-md border border-zinc-300 px-3 py-2">
                                    <input name="notes" value="{{ old('notes', $package->notes) }}" @cannot('hr.compensation.manage') disabled @endcannot class="w-full rounded-md border border-zinc-300 px-3 py-2">
                                    <label class="flex items-center gap-2 text-zinc-600"><input type="checkbox" name="is_active" value="1" @checked($package->is_active) @cannot('hr.compensation.manage') disabled @endcannot> Active</label>
                                    <div class="text-right">@can('hr.compensation.manage')<button class="rounded-md border border-zinc-300 px-3 py-2 font-semibold">Save</button>@endcan</div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-zinc-500">No compensation packages yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>

        <div class="mt-4">{{ $packages->links() }}</div>
    </section>
</x-layouts.app>
