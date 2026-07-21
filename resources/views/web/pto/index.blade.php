<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header title="PTO" description="Review approvals, upcoming time off, balances, and request history.">
            <x-slot:action>
                @can('create', App\Models\PtoRequest::class)
                    @if ($currentEmployee || auth()->user()->can('pto.manage'))
                        <a href="{{ route('pto.create') }}" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Submit request</a>
                    @endif
                @endcan
            </x-slot:action>
        </x-page-header>

        @if ($errors->any())
            <div class="mt-6 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <x-panel class="mt-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold">Pending approvals</h2>
                    <p class="mt-1 text-sm text-zinc-500">Requests waiting for your decision.</p>
                </div>
                <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-600">{{ $pendingApprovals->count() }} pending</span>
            </div>

            <div class="mt-4 divide-y divide-zinc-100">
                @forelse ($pendingApprovals as $request)
                    <div class="flex flex-wrap items-start justify-between gap-4 py-4">
                        <div>
                            <p class="font-semibold">{{ $request->employee?->full_name }}</p>
                            <p class="mt-1 text-sm text-zinc-500">{{ $request->policy?->name }} · {{ $request->starts_at?->toDayDateTimeString() }} to {{ $request->ends_at?->toDayDateTimeString() }} · {{ $request->days }} days</p>
                            @if ($request->reason)
                                <p class="mt-2 text-sm text-zinc-700">{{ $request->reason }}</p>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('pto.approve', $request) }}">@csrf<button class="rounded-md bg-zinc-950 px-3 py-2 text-sm font-semibold text-white">Approve</button></form>
                            <form method="POST" action="{{ route('pto.reject', $request) }}">@csrf<button class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold">Reject</button></form>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-sm text-zinc-500">No requests need your approval.</p>
                @endforelse
            </div>
        </x-panel>

        <x-panel class="mt-6">
            <h2 class="font-semibold">Time off calendar</h2>
            <p class="mt-1 text-sm text-zinc-500">Approved absences for the next three months.</p>

            <div class="mt-5 grid gap-5 xl:grid-cols-3">
                @foreach ($calendarMonths as $month)
                    <section>
                        <h3 class="text-sm font-semibold">{{ $month['name'] }}</h3>
                        <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase text-zinc-500">
                            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                                <div>{{ $weekday }}</div>
                            @endforeach
                        </div>
                        <div class="mt-1 grid grid-cols-7 gap-1">
                            @for ($blank = 0; $blank < $month['leadingBlanks']; $blank++)
                                <div class="min-h-20 rounded-md bg-zinc-50"></div>
                            @endfor
                            @foreach ($month['days'] as $day)
                                <div class="min-h-20 rounded-md border border-zinc-200 bg-white p-1.5 text-xs">
                                    <div class="font-semibold text-zinc-700">{{ $day['day'] }}</div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach ($day['events'] as $event)
                                            <span title="{{ $event['name'] }}" class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-zinc-950 px-1.5 text-[10px] font-semibold text-white">{{ $event['initials'] }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </x-panel>

        <x-panel class="mt-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold">PTO history</h2>
                    <p class="mt-1 text-sm text-zinc-500">Submitted, approved, rejected, and canceled requests.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('pto.index') }}" class="mt-4 grid gap-4 lg:grid-cols-5">
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
                    Employee
                    <select name="employee_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Anyone</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Policy
                    <select name="pto_policy_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any policy</option>
                        @foreach ($policies as $policy)
                            <option value="{{ $policy->id }}" @selected((string) ($filters['pto_policy_id'] ?? '') === (string) $policy->id)>{{ $policy->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Starts from
                    <input name="starts_from" type="date" value="{{ $filters['starts_from'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <label class="block text-sm font-medium">
                    Starts until
                    <input name="starts_until" type="date" value="{{ $filters['starts_until'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <div class="flex items-end justify-end gap-3 lg:col-span-5">
                    <a href="{{ route('pto.index') }}" class="text-sm font-medium text-zinc-600">Clear</a>
                    <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Filter</button>
                </div>
            </form>

            <div class="mt-5 overflow-hidden rounded-lg border border-zinc-200">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Employee</th>
                            <th class="px-4 py-3 font-medium">Dates</th>
                            <th class="px-4 py-3 font-medium">Days</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($requests as $request)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $request->employee?->full_name }}</div>
                                    <div class="text-zinc-500">{{ $request->policy?->name }}</div>
                                </td>
                                <td class="px-4 py-3 text-zinc-600">{{ $request->starts_at?->toDayDateTimeString() }} to {{ $request->ends_at?->toDayDateTimeString() }}</td>
                                <td class="px-4 py-3 text-zinc-600">{{ $request->days }}</td>
                                <td class="px-4 py-3 text-zinc-600">
                                    <div>{{ str($request->status?->value)->title() }}</div>
                                    @if ($request->reason)
                                        <div class="mt-1 text-zinc-500">{{ $request->reason }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @can('cancel', $request)
                                        <form method="POST" action="{{ route('pto.cancel', $request) }}">@csrf<button class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold">Cancel</button></form>
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">No PTO requests.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $requests->links() }}</div>
        </x-panel>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <x-panel class="overflow-hidden p-0">
                <div class="border-b border-zinc-200 px-4 py-3">
                    <h2 class="font-semibold">Balances</h2>
                </div>
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Balance</th>
                            <th class="px-4 py-3 font-medium">Available</th>
                            <th class="px-4 py-3 font-medium">Pending</th>
                            <th class="px-4 py-3 font-medium">Remaining</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($balances as $balance)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $balance->employee?->full_name }}</div>
                                    <div class="text-zinc-500">{{ $balance->policy?->name }} · {{ $balance->period_start?->toDateString() }} to {{ $balance->period_end?->toDateString() }}</div>
                                </td>
                                <td class="px-4 py-3 text-zinc-600">{{ $balance->available_days }}</td>
                                <td class="px-4 py-3 text-zinc-600">{{ $balance->pending_days }}</td>
                                <td class="px-4 py-3 text-zinc-600">{{ number_format((float) $balance->available_days - (float) $balance->pending_days, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">No PTO balances yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-panel>

            @if ($canAdjustPto)
                <x-panel>
                    <h2 class="font-semibold">Manual adjustment</h2>
                    <p class="mt-1 text-sm text-zinc-500">Correct a PTO balance in half-day increments.</p>
                    <form method="POST" action="{{ route('pto.adjustments.store') }}" class="mt-4 grid gap-4">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-medium">
                                Employee
                                <select name="employee_id" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                                    <option value="">Choose employee</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->full_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-sm font-medium">
                                Policy
                                <select name="pto_policy_id" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                                    @foreach ($policies as $policy)
                                        <option value="{{ $policy->id }}" @selected((string) old('pto_policy_id') === (string) $policy->id)>{{ $policy->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-sm font-medium">
                                Effective date
                                <input name="effective_date" type="date" value="{{ old('effective_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                            </label>
                            <label class="block text-sm font-medium">
                                Adjustment days
                                <input name="days" type="number" min="-365" max="365" step="0.5" value="{{ old('days') }}" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                            </label>
                        </div>
                        <label class="block text-sm font-medium">
                            Reason
                            <textarea name="reason" rows="3" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">{{ old('reason') }}</textarea>
                        </label>
                        <div class="flex justify-end"><button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Save adjustment</button></div>
                    </form>
                </x-panel>
            @endif
        </div>
    </section>
</x-layouts.app>
