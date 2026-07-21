<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\PositionResource;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return view('web.positions.index', [
            'positions' => Position::query()
                ->withCount('employees')
                ->search($filters['q'] ?? null)
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse|PositionResource
    {
        $this->authorizeManage($request);

        $position = Position::create($this->validated($request));

        if ($request->expectsJson()) {
            return new PositionResource($position);
        }

        return redirect()->route('positions.index')->with('status', 'Position created.');
    }

    public function update(Request $request, Position $position): RedirectResponse|PositionResource
    {
        $this->authorizeManage($request);

        $position->update($this->validated($request, $position));

        if ($request->expectsJson()) {
            return new PositionResource($position);
        }

        return redirect()->route('positions.index')->with('status', 'Position updated.');
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
