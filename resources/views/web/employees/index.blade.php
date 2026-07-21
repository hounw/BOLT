<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header title="Employees" description="Manage employee records, manager relationships, and HR entry points.">
            <x-slot:action>
                @can('create', App\Models\Employee::class)
                    <a href="{{ route('employees.create') }}" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">New employee</a>
                @endcan
            </x-slot:action>
        </x-page-header>

        <x-panel class="mt-6">
            <form method="GET" action="{{ route('employees.index') }}" class="grid gap-4 lg:grid-cols-4">
                <label class="block text-sm font-medium">
                    Search
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <label class="block text-sm font-medium">
                    Status
                    <select name="status" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str($status->value)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Department
                    <select name="department" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any department</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department }}" @selected(($filters['department'] ?? '') === $department)>{{ $department }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Manager
                    <select name="manager_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Anyone</option>
                        @foreach ($managers as $manager)
                            <option value="{{ $manager->id }}" @selected((string) ($filters['manager_id'] ?? '') === (string) $manager->id)>{{ $manager->full_name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end justify-end gap-3 lg:col-span-4">
                    <a href="{{ route('employees.index') }}" class="text-sm font-medium text-zinc-600">Clear</a>
                    <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Filter</button>
                </div>
            </form>
        </x-panel>

        <x-panel class="mt-6 overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Department</th>
                        <th class="px-4 py-3 font-medium">Manager</th>
                        <th class="px-4 py-3 font-medium">Login</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($employees as $employee)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-employee-avatar :employee="$employee" size="sm" />
                                    <a class="font-medium hover:underline" href="{{ route('employees.show', $employee) }}">{{ $employee->full_name }}</a>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-zinc-600">{{ $employee->department ?: '—' }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $employee->manager?->full_name ?: '—' }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $employee->user?->email ?: '—' }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ str($employee->status?->value)->replace('_', ' ')->title() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">No employees yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>

        <div class="mt-4">{{ $employees->links() }}</div>
    </section>
</x-layouts.app>
