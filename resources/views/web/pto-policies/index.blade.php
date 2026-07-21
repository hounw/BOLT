<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header title="PTO policies" description="Configure allowance, carryover, accrual, and approval paths.">
            <x-slot:action>
                @can('create', App\Models\PtoPolicy::class)
                    <a href="{{ route('pto-policies.create') }}" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">New policy</a>
                @endcan
            </x-slot:action>
        </x-page-header>

        <x-panel class="mt-6 overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Policy</th>
                        <th class="px-4 py-3 font-medium">Allowance</th>
                        <th class="px-4 py-3 font-medium">Carryover</th>
                        <th class="px-4 py-3 font-medium">Calendar</th>
                        <th class="px-4 py-3 font-medium">Approval</th>
                        <th class="px-4 py-3 font-medium">Balances</th>
                        <th class="px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($policies as $policy)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $policy->name }}</div>
                                <div class="text-zinc-500">
                                    {{ str($policy->accrual_type?->value)->replace('_', ' ')->title() }} · {{ $policy->accumulationFrequencyLabel() }}
                                    @if ($policy->is_default)
                                        · Default
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-zinc-600">{{ $policy->annual_allowance_days }} days</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $policy->carryover_days }} days</td>
                            <td class="px-4 py-3 text-zinc-600">
                                <div>{{ count($policy->workingDays()) }} working days</div>
                                <div class="text-zinc-500">{{ count($policy->holidayDates()) }} holidays · {{ $policy->allow_negative_balance ? 'Negative allowed' : 'No negative PTO' }}</div>
                            </td>
                            <td class="px-4 py-3 text-zinc-600">{{ str($policy->approval_strategy)->replace('_', ' ')->title() }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $policy->balances_count }}</td>
                            <td class="px-4 py-3 text-right">
                                @can('update', $policy)
                                    <a href="{{ route('pto-policies.edit', $policy) }}" class="text-sm font-medium text-zinc-700">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-zinc-500">No PTO policies yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>

        <div class="mt-4">{{ $policies->links() }}</div>
    </section>
</x-layouts.app>
