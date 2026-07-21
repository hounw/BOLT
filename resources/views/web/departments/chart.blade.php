<x-layouts.app>
    <section class="mx-auto w-full max-w-6xl px-6 py-8">
        <x-page-header title="Department chart" description="View department hierarchy with nested teams and assigned employees." />
        <div class="mt-4">
            <x-link-button :href="route('departments.index')" label="Manage departments" />
        </div>

        @if ($departments->isEmpty() && $unassignedEmployees->isEmpty())
            <x-panel class="mt-6">
                <p class="text-sm text-zinc-600">No departments or employees have been created yet.</p>
            </x-panel>
        @else
            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <section class="min-w-0">
                    @if ($departments->isEmpty())
                        <x-panel>
                            <p class="text-sm text-zinc-600">No departments have been created yet.</p>
                        </x-panel>
                    @else
                        <div class="org-chart-wrap">
                            <ul class="org-chart min-w-max">
                                @foreach ($departments as $department)
                                    @include('web.departments._chart-node', ['department' => $department])
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>

                <aside class="rounded-md border border-zinc-200 bg-white p-4 shadow-sm">
                    <h2 class="font-semibold">Unassigned employees</h2>
                    @if ($unassignedEmployees->isEmpty())
                        <p class="mt-3 text-sm text-zinc-600">Every employee is assigned to a department.</p>
                    @else
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($unassignedEmployees as $employee)
                                <li class="rounded-md bg-zinc-50 px-3 py-2">
                                    <span class="font-medium">{{ $employee->first_name }} {{ $employee->last_name }}</span>
                                    @if ($employee->title)
                                        <span class="block text-xs text-zinc-500">{{ $employee->title }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </aside>
            </div>
        @endif
    </section>
</x-layouts.app>
