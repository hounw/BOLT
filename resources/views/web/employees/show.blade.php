<x-layouts.app>
    <section class="mx-auto w-full max-w-5xl px-6 py-8">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <x-employee-avatar :employee="$employee" size="lg" />
                <div>
                    <h1 class="text-2xl font-semibold">{{ $employee->first_name }} {{ $employee->last_name }}</h1>
                    <p class="mt-1 max-w-3xl text-sm text-zinc-600">{{ $employee->title ?: 'Employee record' }}</p>
                </div>
            </div>
            <div>
                @can('update', $employee)
                    <a href="{{ route('employees.edit', $employee) }}" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Edit</a>
                @endcan
            </div>
        </header>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-panel>
                <h2 class="font-semibold">Profile</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div><dt class="text-zinc-500">Department</dt><dd>{{ $employee->department ?: '—' }}</dd></div>
                    <div><dt class="text-zinc-500">Position</dt><dd>{{ $employee->title ?: '—' }}</dd></div>
                    <div><dt class="text-zinc-500">Manager</dt><dd>{{ $employee->manager ? $employee->manager->first_name.' '.$employee->manager->last_name : '—' }}</dd></div>
                    <div><dt class="text-zinc-500">Work email</dt><dd>{{ $employee->work_email ?: '—' }}</dd></div>
                    <div><dt class="text-zinc-500">Login user</dt><dd>{{ $employee->user ? $employee->user->name.' - '.$employee->user->email : '—' }}</dd></div>
                    <div><dt class="text-zinc-500">Status</dt><dd>{{ str($employee->status?->value)->replace('_', ' ')->title() }}</dd></div>
                </dl>
            </x-panel>
            <x-panel>
                <h2 class="font-semibold">PTO balances</h2>
                <div class="mt-4 divide-y divide-zinc-100 text-sm">
                    @forelse ($employee->ptoBalances as $balance)
                        <div class="py-3">
                            <div class="font-medium">{{ $balance->policy?->name }}</div>
                            <div class="text-zinc-600">{{ $balance->available_days }} available · {{ $balance->used_days }} used · {{ $balance->pending_days }} pending</div>
                            <div class="text-zinc-500">{{ $balance->period_start?->toDateString() }} to {{ $balance->period_end?->toDateString() }}</div>
                        </div>
                    @empty
                        <p class="py-6 text-zinc-500">No PTO balances.</p>
                    @endforelse
                </div>
            </x-panel>
        </div>

        @can('update', $employee)
            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <x-panel>
                    <h2 class="font-semibold">Private HR details</h2>
                    @php($privateHr = $employee->private_hr_data ?? [])
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div><dt class="text-zinc-500">Personal email</dt><dd>{{ $employee->personal_email ?: '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Phone</dt><dd>{{ $employee->phone ?: '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Address</dt><dd>{{ collect([$privateHr['address_line_1'] ?? null, $privateHr['address_line_2'] ?? null, $privateHr['city'] ?? null, $privateHr['region'] ?? null, $privateHr['postal_code'] ?? null, $privateHr['country'] ?? null])->filter()->join(', ') ?: '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Tax ID</dt><dd>{{ $privateHr['tax_id'] ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Government ID</dt><dd>{{ $privateHr['government_id'] ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Medical notes</dt><dd>{{ $privateHr['medical_notes'] ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Accommodations</dt><dd>{{ $privateHr['accommodations'] ?? '—' }}</dd></div>
                    </dl>
                </x-panel>
                <x-panel>
                    <h2 class="font-semibold">Emergency contact</h2>
                    @php($emergency = $employee->emergency_contact ?? [])
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div><dt class="text-zinc-500">Name</dt><dd>{{ $emergency['name'] ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Relationship</dt><dd>{{ $emergency['relationship'] ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Phone</dt><dd>{{ $emergency['phone'] ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Email</dt><dd>{{ $emergency['email'] ?? '—' }}</dd></div>
                    </dl>
                </x-panel>
            </div>
        @endcan

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-panel>
                <h2 class="font-semibold">PTO requests</h2>
                <div class="mt-4 divide-y divide-zinc-100 text-sm">
                    @forelse ($employee->ptoRequests as $request)
                        <div class="py-3">{{ $request->starts_at?->toDateString() }} · {{ $request->days }} days · {{ $request->status?->value }}</div>
                    @empty
                        <p class="py-6 text-zinc-500">No PTO requests.</p>
                    @endforelse
                </div>
            </x-panel>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            @can('viewCompensation', $employee)
                <x-panel>
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="font-semibold">Compensation</h2>
                    </div>

                    <form method="GET" action="{{ route('employees.show', $employee) }}" class="mt-4 grid gap-3 text-sm">
                        <div class="grid gap-3 md:grid-cols-3">
                            <label class="block font-medium">Type
                                <select name="compensation_type" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                                    <option value="">Any type</option>
                                    @foreach ($compensationTypes as $type)
                                        <option value="{{ $type }}" @selected(($compensationFilters['compensation_type'] ?? '') === $type)>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block font-medium">From<input name="compensation_from" type="date" value="{{ $compensationFilters['compensation_from'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                            <label class="block font-medium">Until<input name="compensation_until" type="date" value="{{ $compensationFilters['compensation_until'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        </div>
                        <input type="hidden" name="benefit_type" value="{{ $benefitFilters['benefit_type'] ?? '' }}">
                        <input type="hidden" name="benefit_from" value="{{ $benefitFilters['benefit_from'] ?? '' }}">
                        <input type="hidden" name="benefit_until" value="{{ $benefitFilters['benefit_until'] ?? '' }}">
                        <div class="flex justify-end gap-3">
                            <a href="{{ route('employees.show', $employee) }}" class="font-medium text-zinc-600">Clear</a>
                            <button class="rounded-md border border-zinc-300 px-3 py-2 font-semibold">Filter</button>
                        </div>
                    </form>

                    <div class="mt-4 divide-y divide-zinc-100 text-sm">
                        @forelse ($employee->compensationHistories as $history)
                            <div class="py-3">
                                <div class="font-medium">{{ $history->effective_date?->toDateString() }} - {{ $history->currency }} {{ $history->amount }}</div>
                                <div class="text-zinc-600">{{ $history->type ?: 'Compensation' }}</div>
                                @if ($history->notes)
                                    <div class="mt-1 text-zinc-500">{{ $history->notes }}</div>
                                @endif
                            </div>
                        @empty
                            <p class="py-6 text-zinc-500">No compensation history.</p>
                        @endforelse
                    </div>

                    @can('manageCompensation', $employee)
                        <form method="POST" action="{{ route('employees.compensation.store', $employee) }}" class="mt-5 grid gap-3 border-t border-zinc-100 pt-5">
                            @csrf
                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="block text-sm font-medium">Effective date<input name="effective_date" type="date" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                                <label class="block text-sm font-medium">Amount<x-money-input name="amount" :currency="$mainCurrency" :symbol="$mainCurrencySymbol" required /></label>
                                <label class="block text-sm font-medium">Type<input name="type" value="salary" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                            </div>
                            <label class="block text-sm font-medium">Notes<textarea name="notes" rows="3" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></textarea></label>
                            <div class="flex justify-end"><button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Add compensation</button></div>
                        </form>
                    @endcan
                </x-panel>
            @endcan

            @can('viewBenefits', $employee)
                <x-panel>
                    <h2 class="font-semibold">Benefits and bonuses</h2>

                    <form method="GET" action="{{ route('employees.show', $employee) }}" class="mt-4 grid gap-3 text-sm">
                        <div class="grid gap-3 md:grid-cols-3">
                            <label class="block font-medium">Type
                                <select name="benefit_type" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                                    <option value="">Any type</option>
                                    @foreach ($benefitTypes as $type)
                                        <option value="{{ $type }}" @selected(($benefitFilters['benefit_type'] ?? '') === $type)>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block font-medium">Starts from<input name="benefit_from" type="date" value="{{ $benefitFilters['benefit_from'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                            <label class="block font-medium">Starts until<input name="benefit_until" type="date" value="{{ $benefitFilters['benefit_until'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        </div>
                        <input type="hidden" name="compensation_type" value="{{ $compensationFilters['compensation_type'] ?? '' }}">
                        <input type="hidden" name="compensation_from" value="{{ $compensationFilters['compensation_from'] ?? '' }}">
                        <input type="hidden" name="compensation_until" value="{{ $compensationFilters['compensation_until'] ?? '' }}">
                        <div class="flex justify-end gap-3">
                            <a href="{{ route('employees.show', $employee) }}" class="font-medium text-zinc-600">Clear</a>
                            <button class="rounded-md border border-zinc-300 px-3 py-2 font-semibold">Filter</button>
                        </div>
                    </form>

                    <div class="mt-4 divide-y divide-zinc-100 text-sm">
                        @forelse ($employee->benefitHistories as $history)
                            <div class="py-3">
                                <div class="font-medium">{{ $history->type }}</div>
                                <div class="text-zinc-600">
                                    {{ $history->value ? 'Value '.$history->value : 'No value recorded' }}
                                    @if ($history->starts_on)
                                        - starts {{ $history->starts_on->toDateString() }}
                                    @endif
                                    @if ($history->ends_on)
                                        - ends {{ $history->ends_on->toDateString() }}
                                    @endif
                                </div>
                                @if ($history->notes)
                                    <div class="mt-1 text-zinc-500">{{ $history->notes }}</div>
                                @endif
                            </div>
                        @empty
                            <p class="py-6 text-zinc-500">No benefit or bonus history.</p>
                        @endforelse
                    </div>

                    @can('manageBenefits', $employee)
                        <form method="POST" action="{{ route('employees.benefits.store', $employee) }}" class="mt-5 grid gap-3 border-t border-zinc-100 pt-5">
                            @csrf
                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="block text-sm font-medium">Type<input name="type" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                                <label class="block text-sm font-medium">Value<input name="value" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                                <label class="block text-sm font-medium">Starts on<input name="starts_on" type="date" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                                <label class="block text-sm font-medium">Ends on<input name="ends_on" type="date" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                            </div>
                            <label class="block text-sm font-medium">Notes<textarea name="notes" rows="3" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></textarea></label>
                            <div class="flex justify-end"><button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Add benefit</button></div>
                        </form>
                    @endcan
                </x-panel>
            @endcan
        </div>

        <div class="mt-6">
            <x-attachments-panel :attachments="$employee->attachments" attachable-type="employees" :attachable-id="$employee->id" />
        </div>
    </section>
</x-layouts.app>
