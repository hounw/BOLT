<x-layouts.app>
    <section class="mx-auto w-full max-w-6xl px-6 py-8">
        <x-page-header title="Departments" description="Manage the department list used by employee records." />
        <div class="mt-4">
            <x-link-button :href="route('departments.chart')" label="Department chart" />
        </div>

        <x-panel class="mt-6">
            <form method="GET" action="{{ route('departments.index') }}" class="flex flex-wrap items-end gap-3">
                <label class="block min-w-72 flex-1 text-sm font-medium">
                    Search
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <a href="{{ route('departments.index') }}" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">Clear</a>
                <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Filter</button>
            </form>
        </x-panel>

        @can('employees.manage')
            <x-panel class="mt-6">
                <h2 class="font-semibold">New department</h2>
                <form method="POST" action="{{ route('departments.store') }}" class="mt-4 grid gap-4 lg:grid-cols-[1fr_1fr_2fr_auto_auto]">
                    @csrf
                    <label class="block text-sm font-medium">Name<input name="name" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Parent
                        <select name="parent_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                            <option value="">None</option>
                            @foreach ($parentOptions as $option)
                                <option value="{{ $option->id }}" @selected((int) old('parent_id') === $option->id)>{{ $option->pathName() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium">Description<input name="description" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="flex items-end gap-2 pb-2 text-sm font-medium"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                    <div class="flex items-end"><button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Save</button></div>
                </form>
            </x-panel>
        @endcan

        <x-panel class="mt-6 overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500">
                    <tr><th class="px-4 py-3 font-medium">Name</th><th class="px-4 py-3 font-medium">Parent</th><th class="px-4 py-3 font-medium">Description</th><th class="px-4 py-3 font-medium">Employees</th><th class="px-4 py-3 font-medium">Status</th><th class="px-4 py-3 font-medium"></th></tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($departments as $department)
                        <tr>
                            <td colspan="6" class="px-4 py-3">
                                <form method="POST" action="{{ route('departments.update', $department) }}" class="grid gap-3 lg:grid-cols-[1fr_1fr_2fr_6rem_7rem_5rem] lg:items-center">
                                    @csrf
                                    @method('PUT')
                                    <input name="name" value="{{ old('name', $department->name) }}" @cannot('employees.manage') disabled @endcannot class="w-full rounded-md border border-zinc-300 px-3 py-2">
                                    <select name="parent_id" @cannot('employees.manage') disabled @endcannot class="w-full rounded-md border border-zinc-300 px-3 py-2">
                                        <option value="">None</option>
                                        @foreach ($parentOptions as $option)
                                            @continue($option->id === $department->id)
                                            <option value="{{ $option->id }}" @selected((int) old('parent_id', $department->parent_id) === $option->id)>{{ $option->pathName() }}</option>
                                        @endforeach
                                    </select>
                                    <input name="description" value="{{ old('description', $department->description) }}" @cannot('employees.manage') disabled @endcannot class="w-full rounded-md border border-zinc-300 px-3 py-2">
                                    <div class="text-zinc-600">{{ $department->employees_count }}</div>
                                    <label class="flex items-center gap-2 text-zinc-600"><input type="checkbox" name="is_active" value="1" @checked($department->is_active) @cannot('employees.manage') disabled @endcannot> Active</label>
                                    <div class="text-right">@can('employees.manage')<button class="rounded-md border border-zinc-300 px-3 py-2 font-semibold">Save</button>@endcan</div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No departments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>

        <div class="mt-4">{{ $departments->links() }}</div>
    </section>
</x-layouts.app>
