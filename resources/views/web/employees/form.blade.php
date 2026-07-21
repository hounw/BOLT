<x-layouts.app>
    @php
        $loginMode = old('login_user_mode', $employee->user_id ? 'existing' : 'none');
        $privateHr = old('private_hr_data', $employee->private_hr_data ?? []);
        $emergency = old('emergency_contact', $employee->emergency_contact ?? []);
    @endphp

    <section class="mx-auto w-full max-w-5xl px-6 py-8">
        <x-page-header :title="$employee->exists ? 'Edit employee' : 'New employee'" />

        <form method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6" data-employee-form>
            @csrf
            @if ($employee->exists)
                @method('PUT')
            @endif

            <x-panel>
                <h2 class="font-semibold">Basics</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium">First name<input name="first_name" value="{{ old('first_name', $employee->first_name) }}" required data-employee-first-name class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Last name<input name="last_name" value="{{ old('last_name', $employee->last_name) }}" required data-employee-last-name class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Work email<input name="work_email" type="email" value="{{ old('work_email', $employee->work_email) }}" data-employee-work-email class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Employee number<input name="employee_number" value="{{ old('employee_number', $employee->employee_number) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium">Photo<input name="photo" type="file" accept="image/*" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        @if ($employee->photo_path)
                            <div class="mt-3 flex items-center gap-3 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm">
                                <x-employee-avatar :employee="$employee" size="sm" />
                                <span class="text-zinc-600">Current employee photo</span>
                                <label class="ml-auto flex items-center gap-2 font-medium"><input name="remove_photo" type="checkbox" value="1"> Remove</label>
                            </div>
                        @else
                            <p class="mt-1 text-xs text-zinc-500">Optional. Stored privately and only shown to users who can view this employee.</p>
                        @endif
                    </div>
                    <label class="block text-sm font-medium">Department
                        <select name="department_id" data-quick-select="department" data-current="{{ old('department_id', $employee->department_id) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                            <option value="">None</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected((int) old('department_id', $employee->department_id) === $department->id)>{{ $department->pathName() }}</option>
                            @endforeach
                            @can('employees.manage')
                                <option value="__create">Create new department...</option>
                            @endcan
                        </select>
                    </label>
                    <label class="block text-sm font-medium">Position
                        <select name="position_id" data-quick-select="position" data-current="{{ old('position_id', $employee->position_id) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                            <option value="">None</option>
                            @foreach ($positions as $position)
                                <option value="{{ $position->id }}" @selected((int) old('position_id', $employee->position_id) === $position->id)>{{ $position->name }}</option>
                            @endforeach
                            @can('employees.manage')
                                <option value="__create">Create new position...</option>
                            @endcan
                        </select>
                    </label>
                    <label class="block text-sm font-medium">Status<select name="status" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $employee->status?->value ?? 'active') === $status->value)>{{ str($status->value)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
                    <label class="block text-sm font-medium">Manager<select name="manager_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"><option value="">None</option>@foreach ($managers as $manager)<option value="{{ $manager->id }}" @selected((int) old('manager_id', $employee->manager_id) === $manager->id)>{{ $manager->first_name }} {{ $manager->last_name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-medium">Start date<input name="start_date" type="date" value="{{ old('start_date', $employee->start_date?->toDateString()) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">End date<input name="end_date" type="date" value="{{ old('end_date', $employee->end_date?->toDateString()) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                </div>
            </x-panel>

            <x-panel>
                <h2 class="font-semibold">Login access</h2>
                @if ($canManageAccess)
                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                        <label class="flex items-center gap-2 rounded-md border border-zinc-200 px-3 py-2 text-sm"><input type="radio" name="login_user_mode" value="none" @checked($loginMode === 'none') data-login-mode> No login</label>
                        <label class="flex items-center gap-2 rounded-md border border-zinc-200 px-3 py-2 text-sm"><input type="radio" name="login_user_mode" value="existing" @checked($loginMode === 'existing') data-login-mode> Existing user</label>
                        <label class="flex items-center gap-2 rounded-md border border-zinc-200 px-3 py-2 text-sm"><input type="radio" name="login_user_mode" value="create" @checked($loginMode === 'create') data-login-mode> Create user</label>
                    </div>

                    <div class="mt-4" data-login-panel="existing">
                        <label class="block text-sm font-medium">Login user
                            <select name="user_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                                <option value="">None</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected((int) old('user_id', $employee->user_id) === $user->id)>{{ $user->name }} - {{ $user->email }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2" data-login-panel="create">
                        <input type="hidden" name="new_user_name" value="{{ old('new_user_name', trim($employee->first_name.' '.$employee->last_name)) }}" data-login-user-name>
                        <input type="hidden" name="new_user_email" value="{{ old('new_user_email', $employee->work_email) }}" data-login-user-email>
                        <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm">
                            <span class="block font-medium text-zinc-700">Login name</span>
                            <span class="mt-1 block text-zinc-900" data-login-user-name-preview>Use employee name</span>
                        </div>
                        <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm">
                            <span class="block font-medium text-zinc-700">Login email</span>
                            <span class="mt-1 block text-zinc-900" data-login-user-email-preview>Use work email</span>
                        </div>
                        <label class="block text-sm font-medium">Temporary password<input name="new_user_password" type="password" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium">Confirm password<input name="new_user_password_confirmation" type="password" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <fieldset class="md:col-span-2">
                            <legend class="text-sm font-semibold">Roles</legend>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($roles as $role)
                                    <label class="flex items-center gap-2 rounded-md border border-zinc-200 px-3 py-2 text-sm">
                                        <input type="checkbox" name="new_user_roles[]" value="{{ $role }}" @checked(in_array($role, old('new_user_roles', ['employee']), true))>
                                        <span>{{ str($role)->replace('-', ' ')->title() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                @else
                    <p class="mt-3 text-sm text-zinc-600">Login users are managed by owner-admins in Platform > Access.</p>
                @endif
            </x-panel>

            @can('employees.manage')
                <x-panel>
                    <h2 class="font-semibold">Private HR details</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium">Personal email<input name="personal_email" type="email" value="{{ old('personal_email', $employee->personal_email) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium">Phone<input name="phone" value="{{ old('phone', $employee->phone) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium">Address line 1<input name="private_hr_data[address_line_1]" value="{{ $privateHr['address_line_1'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium">Address line 2<input name="private_hr_data[address_line_2]" value="{{ $privateHr['address_line_2'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium">City<input name="private_hr_data[city]" value="{{ $privateHr['city'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium">Region<input name="private_hr_data[region]" value="{{ $privateHr['region'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium">Postal code<input name="private_hr_data[postal_code]" value="{{ $privateHr['postal_code'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium">Country<input name="private_hr_data[country]" value="{{ $privateHr['country'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium">Tax ID<input name="private_hr_data[tax_id]" value="{{ $privateHr['tax_id'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium">Government ID<input name="private_hr_data[government_id]" value="{{ $privateHr['government_id'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium md:col-span-2">Medical notes<textarea name="private_hr_data[medical_notes]" rows="3" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">{{ $privateHr['medical_notes'] ?? '' }}</textarea></label>
                        <label class="block text-sm font-medium md:col-span-2">Accommodations<textarea name="private_hr_data[accommodations]" rows="3" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">{{ $privateHr['accommodations'] ?? '' }}</textarea></label>
                    </div>
                </x-panel>

                <x-panel>
                    <h2 class="font-semibold">Emergency contact</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium">Name<input name="emergency_contact[name]" value="{{ $emergency['name'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium">Relationship<input name="emergency_contact[relationship]" value="{{ $emergency['relationship'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium">Phone<input name="emergency_contact[phone]" value="{{ $emergency['phone'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium">Email<input name="emergency_contact[email]" type="email" value="{{ $emergency['email'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    </div>
                </x-panel>
            @endcan

            @can('hr.compensation.manage')
                <x-panel>
                    <h2 class="font-semibold">Initial compensation</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium">Compensation package
                            <select name="compensation_package_id" data-quick-select="compensation-package" data-current="{{ old('compensation_package_id') }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                                <option value="">Skip</option>
                                @foreach ($compensationPackages as $package)
                                    <option value="{{ $package->id }}" @selected((int) old('compensation_package_id') === $package->id)>{{ $package->optionLabel() }}</option>
                                @endforeach
                                <option value="__create">Create new compensation package...</option>
                            </select>
                        </label>
                        <label class="block text-sm font-medium">Effective date<input name="compensation_effective_date" type="date" value="{{ old('compensation_effective_date', old('start_date', $employee->start_date?->toDateString())) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    </div>
                </x-panel>
            @endcan

            @can('pto.manage')
                <x-panel>
                    <h2 class="font-semibold">Starting PTO balance</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium">PTO policy
                            <select name="starting_pto_policy_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                                <option value="">Skip</option>
                                @foreach ($ptoPolicies as $policy)
                                    <option value="{{ $policy->id }}" @selected((int) old('starting_pto_policy_id') === $policy->id)>{{ $policy->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm font-medium">Available days<input name="starting_pto_available_days" type="number" min="0" step="0.5" value="{{ old('starting_pto_available_days') }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <p class="text-sm text-zinc-600 md:col-span-2">Starting PTO uses the employee start date from Basics. Enter whole or half days only.</p>
                    </div>
                </x-panel>
            @endcan

            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="flex justify-end gap-3">
                <a href="{{ route('employees.index') }}" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">Cancel</a>
                <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Save</button>
            </div>
        </form>

        @can('employees.manage')
            <dialog data-quick-dialog="department" class="modal-dialog">
                <form method="dialog" class="border-b border-zinc-200 px-5 py-4"><div class="flex items-center justify-between gap-4"><h2 class="font-semibold">New department</h2><button class="text-sm font-medium text-zinc-600">Close</button></div></form>
                <form data-quick-form="department" data-action="{{ route('departments.store') }}" class="grid gap-4 p-5">
                    <label class="block text-sm font-medium">Name<input name="name" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Parent
                        <select name="parent_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                            <option value="">None</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->pathName() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium">Description<input name="description" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <input type="hidden" name="is_active" value="1">
                    <p class="hidden text-sm text-red-600" data-quick-error></p>
                    <div class="flex justify-end"><button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Create</button></div>
                </form>
            </dialog>

            <dialog data-quick-dialog="position" class="modal-dialog">
                <form method="dialog" class="border-b border-zinc-200 px-5 py-4"><div class="flex items-center justify-between gap-4"><h2 class="font-semibold">New position</h2><button class="text-sm font-medium text-zinc-600">Close</button></div></form>
                <form data-quick-form="position" data-action="{{ route('positions.store') }}" class="grid gap-4 p-5">
                    <label class="block text-sm font-medium">Name<input name="name" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Description<input name="description" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <input type="hidden" name="is_active" value="1">
                    <p class="hidden text-sm text-red-600" data-quick-error></p>
                    <div class="flex justify-end"><button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Create</button></div>
                </form>
            </dialog>
        @endcan

        @can('hr.compensation.manage')
            <dialog data-quick-dialog="compensation-package" class="modal-dialog">
                <form method="dialog" class="border-b border-zinc-200 px-5 py-4"><div class="flex items-center justify-between gap-4"><h2 class="font-semibold">New compensation package</h2><button class="text-sm font-medium text-zinc-600">Close</button></div></form>
                <form data-quick-form="compensation-package" data-action="{{ route('compensation-packages.store') }}" class="grid gap-4 p-5">
                    <label class="block text-sm font-medium">Name<input name="name" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Amount<x-money-input name="amount" :currency="$mainCurrency" :symbol="$mainCurrencySymbol" required /></label>
                    <label class="block text-sm font-medium">Amount basis<select name="amount_basis" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">@foreach (\App\Models\CompensationPackage::AMOUNT_BASES as $value => $label)<option value="{{ $value }}" @selected($value === 'annual')>{{ $label }}</option>@endforeach</select></label>
                    <label class="block text-sm font-medium">Pay frequency<select name="payment_frequency" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">@foreach (\App\Models\CompensationPackage::PAYMENT_FREQUENCIES as $value => $label)<option value="{{ $value }}" @selected($value === 'monthly')>{{ $label }}</option>@endforeach</select></label>
                    <label class="block text-sm font-medium">Type<input name="type" value="salary" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Notes<textarea name="notes" rows="3" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></textarea></label>
                    <input type="hidden" name="is_active" value="1">
                    <p class="hidden text-sm text-red-600" data-quick-error></p>
                    <div class="flex justify-end"><button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Create</button></div>
                </form>
            </dialog>
        @endcan
    </section>
</x-layouts.app>
