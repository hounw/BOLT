<x-layouts.app>
    <section class="mx-auto w-full max-w-3xl px-6 py-8">
        <x-page-header :title="'Edit access: '.$user->name" :description="$user->email" />

        <x-panel class="mt-6">
            <form method="POST" action="{{ route('access.users.update', $user) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <fieldset>
                    <legend class="text-sm font-semibold">Roles</legend>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($roles as $role)
                            <label class="flex items-center gap-2 rounded-md border border-zinc-200 px-3 py-2 text-sm">
                                <input type="checkbox" name="roles[]" value="{{ $role }}" @checked(in_array($role, old('roles', $user->roles->pluck('name')->all()), true))>
                                <span>{{ str($role)->replace('-', ' ')->title() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('roles')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    @error('roles.*')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </fieldset>

                <label class="block text-sm font-medium">
                    Linked employee
                    <select name="employee_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">No employee link</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) old('employee_id', $user->employee?->id) === (string) $employee->id)>
                                {{ $employee->first_name }} {{ $employee->last_name }}{{ $employee->work_email ? ' · '.$employee->work_email : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('employee_id')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('access.users.index') }}" class="text-sm font-medium text-zinc-600">Cancel</a>
                    <button type="submit" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Save access</button>
                </div>
            </form>
        </x-panel>
    </section>
</x-layouts.app>
