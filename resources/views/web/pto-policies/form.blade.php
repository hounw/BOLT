<x-layouts.app>
    <section class="mx-auto w-full max-w-3xl px-6 py-8">
        <x-page-header :title="$policy->exists ? 'Edit PTO policy' : 'New PTO policy'" />

        <x-panel class="mt-6">
            <form method="POST" action="{{ $policy->exists ? route('pto-policies.update', $policy) : route('pto-policies.store') }}" class="grid gap-5">
                @csrf
                @if ($policy->exists)
                    @method('PUT')
                @endif

                <label class="block text-sm font-medium">
                    Name
                    <input name="name" value="{{ old('name', $policy->name) }}" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium">
                        Annual allowance days
                        <input name="annual_allowance_days" type="number" min="0" max="365" step="0.5" value="{{ old('annual_allowance_days', $policy->annual_allowance_days) }}" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                    </label>
                    <label class="block text-sm font-medium">
                        Carryover days
                        <input name="carryover_days" type="number" min="0" max="365" step="0.5" value="{{ old('carryover_days', $policy->carryover_days) }}" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                    </label>
                    <label class="block text-sm font-medium">
                        Accrual type
                        <select name="accrual_type" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                            @foreach ($accrualTypes as $type)
                                <option value="{{ $type->value }}" @selected(old('accrual_type', $policy->accrual_type?->value) === $type->value)>{{ str($type->value)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium">
                        Accumulation frequency
                        <select name="accumulation_frequency" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                            @foreach ($accumulationFrequencies as $value => $label)
                                <option value="{{ $value }}" @selected(old('accumulation_frequency', $policy->accumulation_frequency ?? 'monthly') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium">
                        Approval strategy
                        <select name="approval_strategy" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                            @foreach ($approvalStrategies as $value => $label)
                                <option value="{{ $value }}" @selected(old('approval_strategy', $policy->approval_strategy) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <fieldset class="rounded-lg border border-zinc-200 p-4">
                    <legend class="px-1 text-sm font-semibold">Policy calendar</legend>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                        @foreach ($workingDayOptions as $value => $label)
                            <label class="flex items-center gap-2 rounded-md border border-zinc-200 px-3 py-2 text-sm font-medium">
                                <input name="working_days[]" type="checkbox" value="{{ $value }}" @checked(in_array($value, old('working_days', $policy->workingDays()), true)) class="rounded border-zinc-300">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <label class="mt-4 block text-sm font-medium">
                        Holidays
                        <textarea name="holidays" rows="3" placeholder="2026-01-01&#10;2026-12-25" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">{{ old('holidays', implode("\n", $policy->holidayDates())) }}</textarea>
                        <span class="mt-1 block text-xs text-zinc-500">One YYYY-MM-DD date per line. Holidays are excluded from automatic PTO day calculations.</span>
                    </label>
                </fieldset>

                <label class="flex items-center gap-2 text-sm font-medium">
                    <input name="allow_negative_balance" type="checkbox" value="1" @checked(old('allow_negative_balance', $policy->allow_negative_balance)) class="rounded border-zinc-300">
                    Allow employees to request PTO beyond their current balance
                </label>

                <label class="flex items-center gap-2 text-sm font-medium">
                    <input name="is_default" type="checkbox" value="1" @checked(old('is_default', $policy->is_default)) class="rounded border-zinc-300">
                    Default policy for new PTO workflows
                </label>

                @if ($errors->any())
                    <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
                @endif

                <div class="flex justify-end gap-3">
                    <a href="{{ route('pto-policies.index') }}" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">Cancel</a>
                    <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Save</button>
                </div>
            </form>
        </x-panel>
    </section>
</x-layouts.app>
