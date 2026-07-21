@php
    $employees = $department->employees ?? collect();
    $children = $department->childrenRecursive ?? collect();
@endphp

<li>
    <article class="org-node">
        <h2 class="font-semibold">{{ $department->name }}</h2>
        @if ($department->description)
            <p class="mt-1 text-sm text-zinc-600">{{ $department->description }}</p>
        @endif

        <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium">
            <span class="rounded-full bg-zinc-100 px-2 py-1 text-zinc-700">{{ $employees->count() }} employees</span>
            @unless ($department->is_active)
                <span class="rounded-full bg-zinc-100 px-2 py-1 text-zinc-500">Inactive</span>
            @endunless
        </div>

        @if ($employees->isNotEmpty())
            <ul class="mt-3 space-y-2 text-sm text-zinc-700">
                @foreach ($employees->take(6) as $employee)
                    <li class="flex items-center gap-2 rounded-md bg-zinc-50 px-3 py-2">
                        <x-employee-avatar :employee="$employee" size="sm" />
                        <span>
                            <span class="block font-medium">{{ $employee->first_name }} {{ $employee->last_name }}</span>
                            @if ($employee->title)
                                <span class="block text-xs text-zinc-500">{{ $employee->title }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
            @if ($employees->count() > 6)
                <p class="mt-2 text-xs text-zinc-500">{{ $employees->count() - 6 }} more employees</p>
            @endif
        @endif
    </article>

    @if ($children->isNotEmpty())
        <ul>
            @foreach ($children as $child)
                @include('web.departments._chart-node', ['department' => $child])
            @endforeach
        </ul>
    @endif
</li>
