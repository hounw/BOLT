@php
    $reports = $employee->reportsRecursive ?? collect();
@endphp

<li>
    <article class="org-node">
        <div class="flex items-start gap-3">
            <x-employee-avatar :employee="$employee" size="md" />
            <div class="min-w-0">
                <h2 class="truncate font-semibold">
                    <a href="{{ route('employees.show', $employee) }}" class="hover:underline">{{ $employee->full_name }}</a>
                </h2>
                <p class="mt-1 text-sm text-zinc-600">{{ $employee->title ?: $employee->position?->name ?: 'No position' }}</p>
                <p class="mt-1 text-xs text-zinc-500">{{ $employee->department ?: $employee->departmentRecord?->name ?: 'No department' }}</p>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium">
            <span class="rounded-full bg-zinc-100 px-2 py-1 text-zinc-700">{{ $reports->count() }} reports</span>
            <span class="rounded-full bg-zinc-100 px-2 py-1 text-zinc-700">{{ str($employee->status?->value ?? 'active')->replace('_', ' ')->title() }}</span>
        </div>
    </article>

    @if ($reports->isNotEmpty())
        <ul>
            @foreach ($reports as $report)
                @include('web.employees._org-node', ['employee' => $report])
            @endforeach
        </ul>
    @endif
</li>
