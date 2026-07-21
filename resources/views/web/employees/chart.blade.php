<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header title="People org chart" description="View reporting lines from managers to direct reports.">
            <x-slot:action>
                <x-link-button :href="route('employees.index')" label="Employees" />
            </x-slot:action>
        </x-page-header>

        @if ($employees->isEmpty())
            <x-panel class="mt-6">
                <p class="text-sm text-zinc-600">No employees have been created yet.</p>
            </x-panel>
        @else
            <div class="org-chart-wrap mt-6">
                <ul class="org-chart min-w-max">
                    @foreach ($employees as $employee)
                        @include('web.employees._org-node', ['employee' => $employee])
                    @endforeach
                </ul>
            </div>

            <p class="mt-3 text-sm text-zinc-500">
                Employees without a manager appear as top-level nodes.
            </p>
        @endif
    </section>
</x-layouts.app>
