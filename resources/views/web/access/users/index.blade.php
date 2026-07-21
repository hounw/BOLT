<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header title="Users & roles" description="Review login users, system roles, and employee record links.">
            <x-slot:action>
                <a href="{{ route('access.users.create') }}" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">New user</a>
            </x-slot:action>
        </x-page-header>

        <x-panel class="mt-6">
            <form method="GET" action="{{ route('access.users.index') }}" class="grid gap-4 md:grid-cols-3">
                <label class="block text-sm font-medium">
                    Search
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <label class="block text-sm font-medium">
                    Role
                    <select name="role" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Employee link
                    <select name="employee_link" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any link</option>
                        <option value="linked" @selected(($filters['employee_link'] ?? '') === 'linked')>Linked</option>
                        <option value="unlinked" @selected(($filters['employee_link'] ?? '') === 'unlinked')>Unlinked</option>
                    </select>
                </label>
                <div class="flex items-end justify-end gap-3 md:col-span-3">
                    <a href="{{ route('access.users.index') }}" class="text-sm font-medium text-zinc-600">Clear</a>
                    <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Filter</button>
                </div>
            </form>
        </x-panel>

        <x-panel class="mt-6 overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">User</th>
                        <th class="px-4 py-3 font-medium">Roles</th>
                        <th class="px-4 py-3 font-medium">Employee</th>
                        <th class="px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $user->name }}</div>
                                <div class="text-zinc-500">{{ $user->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-zinc-600">{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td>
                            <td class="px-4 py-3 text-zinc-600">
                                @if ($user->employee)
                                    {{ $user->employee->first_name }} {{ $user->employee->last_name }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('access.users.edit', $user) }}" class="text-sm font-medium text-zinc-700 hover:underline">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">No users yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>

        <div class="mt-4">{{ $users->links() }}</div>
    </section>
</x-layouts.app>
