<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return view('web.departments.index', [
            'departments' => Department::query()
                ->with('parent.parent')
                ->withCount('employees')
                ->search($filters['q'] ?? null)
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'parentOptions' => Department::query()->with('parent.parent')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function chart(Request $request): View
    {
        $this->authorizeView($request);

        return view('web.departments.chart', [
            'departments' => Department::query()
                ->whereNull('parent_id')
                ->withCount('employees')
                ->with(['childrenRecursive', 'employees' => fn ($query) => $query->orderBy('first_name')->orderBy('last_name')])
                ->orderBy('name')
                ->get(),
            'unassignedEmployees' => Employee::query()
                ->whereNull('department_id')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|DepartmentResource
    {
        $this->authorizeManage($request);

        $department = Department::create($this->validated($request));

        if ($request->expectsJson()) {
            return new DepartmentResource($department->load('parent.parent'));
        }

        return redirect()->route('departments.index')->with('status', 'Department created.');
    }

    public function update(Request $request, Department $department): RedirectResponse|DepartmentResource
    {
        $this->authorizeManage($request);

        $department->update($this->validated($request, $department));

        if ($request->expectsJson()) {
            return new DepartmentResource($department->load('parent.parent'));
        }

        return redirect()->route('departments.index')->with('status', 'Department updated.');
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:120', Rule::unique('departments')->ignore($department)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($department?->wouldCreateCycle($data['parent_id'] ?? null)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A department cannot be inside itself or one of its child departments.',
            ]);
        }

        return $data;
    }

    private function authorizeView(Request $request): void
    {
        abort_unless(
            $request->user()?->can(PermissionName::EmployeesView->value)
            || $request->user()?->can(PermissionName::EmployeesManage->value),
            403
        );
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::EmployeesManage->value), 403);
    }
}
