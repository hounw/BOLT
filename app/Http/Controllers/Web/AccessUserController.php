<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionName;
use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class AccessUserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'string', Rule::in(SystemRole::values())],
            'employee_link' => ['nullable', Rule::in(['linked', 'unlinked'])],
        ]);

        return view('web.access.users.index', [
            'users' => User::query()
                ->with('employee', 'roles')
                ->when($filters['q'] ?? null, function ($query, string $term): void {
                    $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

                    $query->where(function ($query) use ($needle): void {
                        $query->where('name', 'like', $needle)
                            ->orWhere('email', 'like', $needle)
                            ->orWhereHas('employee', function ($query) use ($needle): void {
                                $query->where('first_name', 'like', $needle)
                                    ->orWhere('last_name', 'like', $needle)
                                    ->orWhere('work_email', 'like', $needle);
                            });
                    });
                })
                ->when($filters['role'] ?? null, fn ($query, string $role) => $query->role($role))
                ->when(($filters['employee_link'] ?? null) === 'linked', fn ($query) => $query->has('employee'))
                ->when(($filters['employee_link'] ?? null) === 'unlinked', fn ($query) => $query->doesntHave('employee'))
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'filters' => $filters,
            'roles' => $this->roles(),
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorizeAccess($request);

        return view('web.access.users.edit', [
            'user' => $user->load('employee', 'roles'),
            'roles' => $this->roles(),
            'employees' => $this->availableEmployees($user),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAccess($request);

        return view('web.access.users.create', [
            'roles' => $this->roles(),
            'employees' => $this->availableEmployees(),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(SystemRole::values())],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
        ]);

        $employee = $this->selectedEmployee($data['employee_id'] ?? null);
        $roles = collect($data['roles'])->unique()->values()->all();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->syncRoles($roles);

        if ($employee) {
            $employee->update(['user_id' => $user->id]);
        }

        $user->refresh()->load('employee', 'roles');

        $auditLogger->log('access.user_created', $user, $request->user(), newValues: [
            'roles' => $user->roles()->pluck('name')->sort()->values()->all(),
            'employee_id' => $user->employee?->id,
        ]);

        return redirect()->route('access.users.index')->with('status', 'User created.');
    }

    public function update(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(SystemRole::values())],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
        ]);

        $roles = collect($data['roles'])->unique()->values()->all();

        if ($user->hasRole(SystemRole::OwnerAdmin->value) && ! in_array(SystemRole::OwnerAdmin->value, $roles, true)) {
            $this->ensureAnotherOwnerAdminExists($user);
        }

        $employee = $this->selectedEmployee($data['employee_id'] ?? null, $user);

        $oldValues = [
            'roles' => $user->roles()->pluck('name')->sort()->values()->all(),
            'employee_id' => $user->employee?->id,
        ];

        if ($user->employee && $user->employee->id !== $employee?->id) {
            $user->employee->update(['user_id' => null]);
        }

        if ($employee && $employee->user_id !== $user->id) {
            $employee->update(['user_id' => $user->id]);
        }

        $user->syncRoles($roles);
        $user->refresh()->load('employee', 'roles');

        $auditLogger->log('access.user_updated', $user, $request->user(), $oldValues, [
            'roles' => $user->roles()->pluck('name')->sort()->values()->all(),
            'employee_id' => $user->employee?->id,
        ]);

        return redirect()->route('access.users.index')->with('status', 'User access updated.');
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::ApiClientsManage->value), 403);
    }

    private function roles(): array
    {
        return Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')->all();
    }

    private function availableEmployees(?User $user = null)
    {
        return Employee::query()
            ->whereNull('user_id')
            ->when($user, fn ($query) => $query->orWhere('user_id', $user->id))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    private function selectedEmployee(mixed $employeeId, ?User $user = null): ?Employee
    {
        if (blank($employeeId)) {
            return null;
        }

        $employee = Employee::query()->whereKey($employeeId)->first();

        if ($employee && $employee->user_id && $employee->user_id !== $user?->id) {
            throw ValidationException::withMessages([
                'employee_id' => 'That employee is already linked to another user.',
            ]);
        }

        return $employee;
    }

    private function ensureAnotherOwnerAdminExists(User $user): void
    {
        $anotherOwner = User::role(SystemRole::OwnerAdmin->value)
            ->whereKeyNot($user->id)
            ->exists();

        if (! $anotherOwner) {
            throw ValidationException::withMessages([
                'roles' => 'At least one owner-admin user must remain.',
            ]);
        }
    }
}
