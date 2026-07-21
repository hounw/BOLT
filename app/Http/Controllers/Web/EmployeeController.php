<?php

namespace App\Http\Controllers\Web;

use App\Enums\EmployeeStatus;
use App\Enums\PermissionName;
use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Models\CompensationPackage;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\PtoBalance;
use App\Models\PtoPolicy;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\WebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Employee::class);

        $filters = request()->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(EmployeeStatus::class)],
            'department' => ['nullable', 'string', 'max:120'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        return view('web.employees.index', [
            'employees' => Employee::with(['manager', 'user', 'departmentRecord', 'position'])
                ->search($filters['q'] ?? null)
                ->filterStatus($filters['status'] ?? null)
                ->filterDepartment($filters['department'] ?? null)
                ->managedBy($filters['manager_id'] ?? null)
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'departments' => Department::query()->active()->orderBy('name')->pluck('name')
                ->merge(Employee::query()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'))
                ->unique()
                ->values(),
            'filters' => $filters,
            'managers' => Employee::query()->orderBy('first_name')->orderBy('last_name')->get(),
            'statuses' => EmployeeStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Employee::class);

        return view('web.employees.form', [
            'canManageAccess' => request()->user()->can(PermissionName::ApiClientsManage->value),
            'employee' => new Employee,
            'compensationPackages' => CompensationPackage::active()->orderBy('name')->get(),
            'departments' => Department::active()->with('parent.parent')->orderBy('name')->get(),
            'mainCurrency' => SystemSetting::mainCurrency(),
            'mainCurrencySymbol' => SystemSetting::mainCurrencySymbol(),
            'managers' => Employee::orderBy('first_name')->get(),
            'positions' => Position::active()->orderBy('name')->get(),
            'ptoPolicies' => PtoPolicy::orderByDesc('is_default')->orderBy('name')->get(),
            'roles' => $this->roles(),
            'statuses' => EmployeeStatus::cases(),
            'users' => $this->availableUsers(),
        ]);
    }

    public function chart(): View
    {
        $this->authorize('viewAny', Employee::class);

        return view('web.employees.chart', [
            'employees' => Employee::query()
                ->whereNull('manager_id')
                ->with([
                    'departmentRecord:id,name',
                    'position:id,name',
                    'reportsRecursive',
                ])
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function store(Request $request, WebhookDispatcher $webhooks, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $employee = DB::transaction(function () use ($request, $auditLogger): Employee {
            $data = $this->validated($request);
            $this->resolveReferences($data);
            $this->resolveLoginUser($request, $data, $auditLogger);

            $employee = Employee::create($this->employeePayload($data));
            $this->syncPhoto($request, $employee);
            $this->createOnboardingCompensation($request, $employee, $data);
            $this->createStartingPtoBalance($request, $employee, $data);

            return $employee;
        });

        $webhooks->dispatch('employee.created', ['employee_id' => $employee->id]);

        return redirect()->route('employees.show', $employee)->with('status', 'Employee created.');
    }

    public function show(Request $request, Employee $employee): View
    {
        $this->authorize('view', $employee);

        $filters = $request->validate([
            'compensation_type' => ['nullable', 'string', 'max:80'],
            'compensation_from' => ['nullable', 'date'],
            'compensation_until' => ['nullable', 'date', 'after_or_equal:compensation_from'],
            'benefit_type' => ['nullable', 'string', 'max:120'],
            'benefit_from' => ['nullable', 'date'],
            'benefit_until' => ['nullable', 'date', 'after_or_equal:benefit_from'],
        ]);

        return view('web.employees.show', [
            'employee' => $employee->load([
                'manager',
                'departmentRecord',
                'position',
                'reports',
                'ptoBalances.policy',
                'ptoRequests',
                'user',
                'attachments.uploader',
                'compensationHistories' => fn ($query) => $query
                    ->filterType($filters['compensation_type'] ?? null)
                    ->effectiveOnOrAfter($filters['compensation_from'] ?? null)
                    ->effectiveOnOrBefore($filters['compensation_until'] ?? null)
                    ->latest('effective_date'),
                'benefitHistories' => fn ($query) => $query
                    ->filterType($filters['benefit_type'] ?? null)
                    ->startingOnOrAfter($filters['benefit_from'] ?? null)
                    ->startingOnOrBefore($filters['benefit_until'] ?? null)
                    ->latest('starts_on')
                    ->latest(),
            ]),
            'benefitFilters' => $filters,
            'benefitTypes' => $employee->benefitHistories()->distinct()->orderBy('type')->pluck('type'),
            'compensationFilters' => $filters,
            'compensationTypes' => $employee->compensationHistories()->distinct()->orderBy('type')->pluck('type'),
            'mainCurrency' => SystemSetting::mainCurrency(),
            'mainCurrencySymbol' => SystemSetting::mainCurrencySymbol(),
        ]);
    }

    public function photo(Employee $employee): StreamedResponse
    {
        $this->authorize('view', $employee);

        abort_unless($employee->photo_path && Storage::disk('local')->exists($employee->photo_path), 404);

        return Storage::disk('local')->response($employee->photo_path);
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('update', $employee);

        return view('web.employees.form', [
            'employee' => $employee,
            'canManageAccess' => request()->user()->can(PermissionName::ApiClientsManage->value),
            'compensationPackages' => CompensationPackage::active()->orderBy('name')->get(),
            'departments' => Department::query()
                ->with('parent.parent')
                ->where(fn ($query) => $query->active()->when($employee->department_id, fn ($query) => $query->orWhere('id', $employee->department_id)))
                ->orderBy('name')
                ->get(),
            'managers' => Employee::whereKeyNot($employee->id)->orderBy('first_name')->get(),
            'mainCurrency' => SystemSetting::mainCurrency(),
            'mainCurrencySymbol' => SystemSetting::mainCurrencySymbol(),
            'positions' => Position::query()
                ->where(fn ($query) => $query->active()->when($employee->position_id, fn ($query) => $query->orWhere('id', $employee->position_id)))
                ->orderBy('name')
                ->get(),
            'ptoPolicies' => PtoPolicy::orderByDesc('is_default')->orderBy('name')->get(),
            'roles' => $this->roles(),
            'statuses' => EmployeeStatus::cases(),
            'users' => $this->availableUsers($employee),
        ]);
    }

    public function update(Request $request, Employee $employee, WebhookDispatcher $webhooks, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('update', $employee);

        DB::transaction(function () use ($request, $employee, $auditLogger): void {
            $data = $this->validated($request, $employee);
            $this->resolveReferences($data);
            $this->resolveLoginUser($request, $data, $auditLogger, $employee);
            $employee->update($this->employeePayload($data));
            $this->syncPhoto($request, $employee);
            $this->createOnboardingCompensation($request, $employee, $data);
            $this->createStartingPtoBalance($request, $employee, $data);
        });

        $webhooks->dispatch('employee.updated', ['employee_id' => $employee->id]);

        return redirect()->route('employees.show', $employee)->with('status', 'Employee updated.');
    }

    public function storeCompensation(Request $request, Employee $employee, WebhookDispatcher $webhooks): RedirectResponse
    {
        $this->authorize('manageCompensation', $employee);

        $history = $employee->compensationHistories()->create($this->validatedCompensation($request) + [
            'created_by_id' => $request->user()->id,
            'currency' => SystemSetting::mainCurrency(),
            'type' => $request->input('type', 'salary'),
        ]);

        $webhooks->dispatch('compensation.created', [
            'employee_id' => $employee->id,
            'compensation_history_id' => $history->id,
        ]);

        return redirect()->route('employees.show', $employee)->with('status', 'Compensation entry added.');
    }

    public function storeBenefit(Request $request, Employee $employee, WebhookDispatcher $webhooks): RedirectResponse
    {
        $this->authorize('manageBenefits', $employee);

        $history = $employee->benefitHistories()->create($this->validatedBenefit($request) + [
            'created_by_id' => $request->user()->id,
        ]);

        $webhooks->dispatch('benefit.created', [
            'employee_id' => $employee->id,
            'benefit_history_id' => $history->id,
        ]);

        return redirect()->route('employees.show', $employee)->with('status', 'Benefit entry added.');
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $canManageAccess = $request->user()?->can(PermissionName::ApiClientsManage->value);
        $canManageCompensation = $request->user()?->can(PermissionName::CompensationManage->value);
        $canManagePto = $request->user()?->can(PermissionName::PtoManage->value);
        $startDateRules = ['nullable', 'date'];

        if ($canManagePto) {
            $startDateRules[] = 'required_with:starting_pto_policy_id';
        }

        if ($canManageAccess && $request->input('login_user_mode') === 'create') {
            $request->merge([
                'new_user_name' => trim(collect([$request->input('first_name'), $request->input('last_name')])->filter()->implode(' ')),
                'new_user_email' => $request->input('work_email'),
            ]);
        }

        return $request->validate([
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'login_user_mode' => [$canManageAccess ? 'nullable' : 'prohibited', Rule::in(['none', 'existing', 'create'])],
            'user_id' => [$canManageAccess ? 'nullable' : 'prohibited', 'integer', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employee)],
            'new_user_name' => [$canManageAccess ? 'nullable' : 'prohibited', 'string', 'max:255', 'required_if:login_user_mode,create'],
            'new_user_email' => [$canManageAccess ? 'nullable' : 'prohibited', 'email', 'max:255', 'required_if:login_user_mode,create', Rule::unique('users', 'email')],
            'new_user_password' => [$canManageAccess ? 'nullable' : 'prohibited', 'confirmed', 'required_if:login_user_mode,create', Password::min(8)->letters()->numbers()->symbols()],
            'new_user_roles' => [$canManageAccess ? 'nullable' : 'prohibited', 'array', 'required_if:login_user_mode,create'],
            'new_user_roles.*' => ['string', Rule::in(SystemRole::values())],
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('employees')->ignore($employee)],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'work_email' => ['nullable', 'email', 'max:255', Rule::unique('employees')->ignore($employee)],
            'photo' => ['nullable', 'image', 'max:4096'],
            'remove_photo' => ['nullable', 'boolean'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'department' => ['nullable', 'string', 'max:120'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'title' => ['nullable', 'string', 'max:120'],
            'start_date' => $startDateRules,
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'emergency_contact.name' => ['nullable', 'string', 'max:120'],
            'emergency_contact.relationship' => ['nullable', 'string', 'max:120'],
            'emergency_contact.phone' => ['nullable', 'string', 'max:80'],
            'emergency_contact.email' => ['nullable', 'email', 'max:255'],
            'private_hr_data.address_line_1' => ['nullable', 'string', 'max:255'],
            'private_hr_data.address_line_2' => ['nullable', 'string', 'max:255'],
            'private_hr_data.city' => ['nullable', 'string', 'max:120'],
            'private_hr_data.region' => ['nullable', 'string', 'max:120'],
            'private_hr_data.postal_code' => ['nullable', 'string', 'max:40'],
            'private_hr_data.country' => ['nullable', 'string', 'max:120'],
            'private_hr_data.medical_notes' => ['nullable', 'string', 'max:4000'],
            'private_hr_data.accommodations' => ['nullable', 'string', 'max:4000'],
            'private_hr_data.tax_id' => ['nullable', 'string', 'max:120'],
            'private_hr_data.government_id' => ['nullable', 'string', 'max:120'],
            'compensation_package_id' => [$canManageCompensation ? 'nullable' : 'prohibited', 'integer', 'exists:compensation_packages,id'],
            'compensation_effective_date' => [$canManageCompensation ? 'nullable' : 'prohibited', 'date', 'required_with:compensation_package_id'],
            'starting_pto_policy_id' => [$canManagePto ? 'nullable' : 'prohibited', 'integer', 'exists:pto_policies,id'],
            'starting_pto_available_days' => [$canManagePto ? 'nullable' : 'prohibited', 'numeric', 'min:0', 'multiple_of:0.5', 'required_with:starting_pto_policy_id'],
        ]);
    }

    private function employeePayload(array $data): array
    {
        $emergencyContact = $this->filledNested($data['emergency_contact'] ?? []);
        $privateHrData = $this->filledNested($data['private_hr_data'] ?? []);

        return Arr::only($data, [
            'manager_id',
            'user_id',
            'employee_number',
            'first_name',
            'last_name',
            'work_email',
            'personal_email',
            'phone',
            'status',
            'department_id',
            'department',
            'position_id',
            'title',
            'start_date',
            'end_date',
        ]) + [
            'emergency_contact' => $emergencyContact ?: null,
            'private_hr_data' => $privateHrData ?: null,
        ];
    }

    private function syncPhoto(Request $request, Employee $employee): void
    {
        if ($request->boolean('remove_photo') && $employee->photo_path) {
            Storage::disk('local')->delete($employee->photo_path);
            $employee->forceFill(['photo_path' => null])->save();
        }

        if (! $request->hasFile('photo')) {
            return;
        }

        if ($employee->photo_path) {
            Storage::disk('local')->delete($employee->photo_path);
        }

        $path = $request->file('photo')->store('employee-photos', 'local');
        $employee->forceFill(['photo_path' => $path])->save();
    }

    private function resolveReferences(array &$data): void
    {
        if (filled($data['department_id'] ?? null)) {
            $department = Department::findOrFail($data['department_id']);
            $data['department'] = $department->name;
        } elseif (filled($data['department'] ?? null)) {
            $department = Department::firstOrCreate(['name' => trim($data['department'])], ['is_active' => true]);
            $data['department_id'] = $department->id;
            $data['department'] = $department->name;
        }

        if (filled($data['position_id'] ?? null)) {
            $position = Position::findOrFail($data['position_id']);
            $data['title'] = $position->name;
        } elseif (filled($data['title'] ?? null)) {
            $position = Position::firstOrCreate(['name' => trim($data['title'])], ['is_active' => true]);
            $data['position_id'] = $position->id;
            $data['title'] = $position->name;
        }
    }

    private function resolveLoginUser(Request $request, array &$data, AuditLogger $auditLogger, ?Employee $employee = null): void
    {
        if (! $request->user()?->can(PermissionName::ApiClientsManage->value)) {
            return;
        }

        $mode = $data['login_user_mode'] ?? (filled($data['user_id'] ?? null) ? 'existing' : 'none');

        if ($mode === 'none') {
            $data['user_id'] = null;

            return;
        }

        if ($mode === 'existing') {
            return;
        }

        $roles = collect($data['new_user_roles'] ?? [SystemRole::Employee->value])->unique()->values()->all();

        $user = User::create([
            'name' => $data['new_user_name'],
            'email' => $data['new_user_email'],
            'password' => $data['new_user_password'],
        ]);
        $user->syncRoles($roles);
        $data['user_id'] = $user->id;

        $auditLogger->log('access.user_created', $user, $request->user(), newValues: [
            'roles' => $roles,
            'employee_id' => $employee?->id,
            'source' => 'employee_onboarding',
        ]);
    }

    private function createOnboardingCompensation(Request $request, Employee $employee, array $data): void
    {
        if (blank($data['compensation_package_id'] ?? null)) {
            return;
        }

        if (! $request->user()?->can(PermissionName::CompensationManage->value)) {
            throw ValidationException::withMessages([
                'compensation_package_id' => 'You are not allowed to create compensation history.',
            ]);
        }

        $package = CompensationPackage::active()->findOrFail($data['compensation_package_id']);

        $packageSummary = "Package: {$package->name} ({$package->currency} {$package->amount} {$package->amountBasisLabel()}, {$package->paymentFrequencyLabel()})";

        $employee->compensationHistories()->create([
            'effective_date' => $data['compensation_effective_date'],
            'amount' => $package->amount,
            'currency' => $package->currency,
            'type' => $package->type,
            'notes' => $package->notes ? $packageSummary."\n".$package->notes : $packageSummary,
            'created_by_id' => $request->user()->id,
        ]);
    }

    private function createStartingPtoBalance(Request $request, Employee $employee, array $data): void
    {
        if (blank($data['starting_pto_policy_id'] ?? null)) {
            return;
        }

        if (! $request->user()?->can(PermissionName::PtoManage->value)) {
            throw ValidationException::withMessages([
                'starting_pto_policy_id' => 'You are not allowed to create PTO balances.',
            ]);
        }

        $periodStart = Carbon::parse($data['start_date'])->startOfDay();

        PtoBalance::create([
            'employee_id' => $employee->id,
            'pto_policy_id' => $data['starting_pto_policy_id'],
            'available_days' => (float) $data['starting_pto_available_days'],
            'used_days' => 0,
            'pending_days' => 0,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodStart->copy()->endOfYear()->toDateString(),
        ]);
    }

    private function filledNested(array $values): array
    {
        return collect($values)
            ->filter(fn ($value): bool => filled($value))
            ->all();
    }

    private function validatedCompensation(Request $request): array
    {
        return $request->validate([
            'effective_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'type' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    private function validatedBenefit(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string', 'max:120'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    private function availableUsers(?Employee $employee = null): Collection
    {
        return User::query()
            ->whereDoesntHave('employee')
            ->when($employee?->user_id, fn ($query) => $query->orWhere('id', $employee->user_id))
            ->orderBy('name')
            ->get();
    }

    private function roles(): array
    {
        return Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')->all();
    }
}
