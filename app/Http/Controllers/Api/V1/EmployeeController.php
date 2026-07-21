<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EmployeeStatus;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\CompensationPackage;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\PtoBalance;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Employee::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(EmployeeStatus::class)],
            'department' => ['nullable', 'string', 'max:120'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        return EmployeeResource::collection(
            Employee::query()
                ->with('manager:id,first_name,last_name,user_id', 'departmentRecord:id,name', 'position:id,name')
                ->search($filters['q'] ?? null)
                ->filterStatus($filters['status'] ?? null)
                ->filterDepartment($filters['department'] ?? null)
                ->managedBy($filters['manager_id'] ?? null)
                ->latest()
                ->paginate()
                ->withQueryString()
        );
    }

    public function store(EmployeeRequest $request, WebhookDispatcher $webhooks): EmployeeResource
    {
        $this->authorize('create', Employee::class);

        $employee = DB::transaction(function () use ($request): Employee {
            $data = $request->validated();
            $this->resolveReferences($data);

            $employee = Employee::create($this->employeePayload($data));
            $this->createOnboardingCompensation($request, $employee, $data);
            $this->createStartingPtoBalance($request, $employee, $data);

            return $employee;
        });

        $webhooks->dispatch('employee.created', ['employee_id' => $employee->id]);

        return new EmployeeResource($employee);
    }

    public function show(Employee $employee): EmployeeResource
    {
        $this->authorize('view', $employee);

        return new EmployeeResource($employee);
    }

    public function update(EmployeeRequest $request, Employee $employee, WebhookDispatcher $webhooks): EmployeeResource
    {
        $this->authorize('update', $employee);

        DB::transaction(function () use ($request, $employee): void {
            $data = $request->validated();
            $this->resolveReferences($data);
            $employee->update($this->employeePayload($data));
            $this->createOnboardingCompensation($request, $employee, $data);
            $this->createStartingPtoBalance($request, $employee, $data);
        });

        $webhooks->dispatch('employee.updated', ['employee_id' => $employee->id]);

        return new EmployeeResource($employee);
    }

    private function employeePayload(array $data): array
    {
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
            'emergency_contact',
            'hr_metadata',
            'private_hr_data',
        ]);
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

    private function createOnboardingCompensation(EmployeeRequest $request, Employee $employee, array $data): void
    {
        if (blank($data['compensation_package_id'] ?? null)) {
            return;
        }

        if (! $request->user()?->tokenCan('hr:write') || ! $request->user()?->can(PermissionName::CompensationManage->value)) {
            throw ValidationException::withMessages([
                'compensation_package_id' => 'The token and user must be allowed to create compensation history.',
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

    private function createStartingPtoBalance(EmployeeRequest $request, Employee $employee, array $data): void
    {
        if (blank($data['starting_pto_policy_id'] ?? null)) {
            return;
        }

        if (! $request->user()?->tokenCan('pto:write') || ! $request->user()?->can(PermissionName::PtoManage->value)) {
            throw ValidationException::withMessages([
                'starting_pto_policy_id' => 'The token and user must be allowed to create PTO balances.',
            ]);
        }

        PtoBalance::create([
            'employee_id' => $employee->id,
            'pto_policy_id' => $data['starting_pto_policy_id'],
            'available_days' => $data['starting_pto_available_days'],
            'used_days' => 0,
            'pending_days' => 0,
            'period_start' => $data['starting_pto_period_start'],
            'period_end' => $data['starting_pto_period_end'],
        ]);
    }
}
