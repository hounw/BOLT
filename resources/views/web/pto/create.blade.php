<x-layouts.app>
    <section class="mx-auto w-full max-w-4xl px-6 py-8">
        <x-page-header title="Submit PTO request" description="Choose dates and BOLT will calculate PTO days from the policy calendar." />

        <x-panel class="mt-6">
            <form method="POST" action="{{ route('pto.store') }}" class="grid gap-5" data-pto-request-form>
                @csrf

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium">
                        Employee
                        <select name="employee_id" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                            @if ($currentEmployee && ! auth()->user()->can('pto.manage'))
                                <option value="{{ $currentEmployee->id }}" @selected((string) old('employee_id', $currentEmployee->id) === (string) $currentEmployee->id)>{{ $currentEmployee->full_name }}</option>
                            @else
                                <option value="">Choose employee</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->full_name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </label>

                    <label class="block text-sm font-medium">
                        Policy
                        <select name="pto_policy_id" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                            @foreach ($policies as $policy)
                                <option value="{{ $policy->id }}" @selected((string) old('pto_policy_id', $policies->firstWhere('is_default', true)?->id ?? $policies->first()?->id) === (string) $policy->id)>{{ $policy->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-sm font-medium">
                        Starts at
                        <input name="starts_at" type="datetime-local" value="{{ old('starts_at') }}" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                    </label>

                    <label class="block text-sm font-medium">
                        Ends at
                        <input name="ends_at" type="datetime-local" value="{{ old('ends_at') }}" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                    </label>
                </div>

                <div class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 md:grid-cols-3">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input name="half_day_start" type="checkbox" value="1" @checked(old('half_day_start')) class="rounded border-zinc-300">
                        First day is a half day
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input name="half_day_end" type="checkbox" value="1" @checked(old('half_day_end')) class="rounded border-zinc-300">
                        Last day is a half day
                    </label>
                    <div class="rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm">
                        <div class="text-zinc-500">Calculated PTO</div>
                        <output class="font-semibold" data-pto-days-output>Choose dates</output>
                    </div>
                </div>

                <label class="block text-sm font-medium">
                    Reason
                    <textarea name="reason" rows="4" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">{{ old('reason') }}</textarea>
                </label>

                @if ($errors->any())
                    <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
                @endif

                <div class="flex justify-end gap-3">
                    <a href="{{ route('pto.index') }}" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">Cancel</a>
                    <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Submit request</button>
                </div>
            </form>
        </x-panel>
    </section>

    <script>
        window.BoltPtoPolicies = @json($policies->mapWithKeys(fn ($policy) => [$policy->id => [
            'workingDays' => $policy->workingDays(),
            'holidays' => $policy->holidayDates(),
        ]]));

        document.querySelectorAll('[data-pto-request-form]').forEach((form) => {
            const output = form.querySelector('[data-pto-days-output]');
            const fields = ['pto_policy_id', 'starts_at', 'ends_at', 'half_day_start', 'half_day_end'].map((name) => form.elements[name]);
            const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

            const dateOnly = (value) => value ? new Date(`${value.slice(0, 10)}T00:00:00`) : null;
            const keyFor = (date) => date.toISOString().slice(0, 10);

            const update = () => {
                const policy = window.BoltPtoPolicies[form.elements.pto_policy_id.value];
                const start = dateOnly(form.elements.starts_at.value);
                const end = dateOnly(form.elements.ends_at.value);

                if (!policy || !start || !end) {
                    output.textContent = 'Choose dates';
                    return;
                }

                if (end < start) {
                    output.textContent = 'End date must be after start date';
                    return;
                }

                let dates = [];
                for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
                    const weekday = dayNames[date.getDay()];
                    const key = keyFor(date);

                    if (policy.workingDays.includes(weekday) && !policy.holidays.includes(key)) {
                        dates.push(key);
                    }
                }

                if (dates.length === 0) {
                    output.textContent = '0 days';
                    return;
                }

                let days = dates.length;

                if (form.elements.half_day_start.checked && dates.includes(keyFor(start))) {
                    days -= 0.5;
                }

                if (form.elements.half_day_end.checked && keyFor(start) !== keyFor(end) && dates.includes(keyFor(end))) {
                    days -= 0.5;
                }

                output.textContent = `${Math.max(0.5, days).toFixed(1)} days`;
            };

            fields.forEach((field) => field?.addEventListener('change', update));
            update();
        });
    </script>
</x-layouts.app>
