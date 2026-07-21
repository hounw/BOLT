<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\PositionResource;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeView($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', Rule::in(['1', '0'])],
        ]);

        return PositionResource::collection(
            Position::query()
                ->search($filters['q'] ?? null)
                ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', $filters['is_active'] === '1'))
                ->orderBy('name')
                ->paginate()
                ->withQueryString()
        );
    }

    public function store(Request $request): PositionResource
    {
        $this->authorizeManage($request);

        return new PositionResource(Position::create($this->validated($request)));
    }

    public function update(Request $request, Position $position): PositionResource
    {
        $this->authorizeManage($request);

        $position->update($this->validated($request, $position));

        return new PositionResource($position);
    }

    private function validated(Request $request, ?Position $position = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('positions')->ignore($position)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active', true),
        ];
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
