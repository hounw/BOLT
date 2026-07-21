<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DepartmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeView($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', Rule::in(['1', '0'])],
        ]);

        return DepartmentResource::collection(
            Department::query()
                ->with('parent.parent')
                ->search($filters['q'] ?? null)
                ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', $filters['is_active'] === '1'))
                ->orderBy('name')
                ->paginate()
                ->withQueryString()
        );
    }

    public function store(Request $request): DepartmentResource
    {
        $this->authorizeManage($request);

        return new DepartmentResource(Department::create($this->validated($request))->load('parent.parent'));
    }

    public function update(Request $request, Department $department): DepartmentResource
    {
        $this->authorizeManage($request);

        $department->update($this->validated($request, $department));

        return new DepartmentResource($department->load('parent.parent'));
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
