<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompensationPackageResource;
use App\Models\CompensationPackage;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CompensationPackageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeView($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', Rule::in(['1', '0'])],
        ]);

        return CompensationPackageResource::collection(
            CompensationPackage::query()
                ->search($filters['q'] ?? null)
                ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', $filters['is_active'] === '1'))
                ->orderBy('name')
                ->paginate()
                ->withQueryString()
        );
    }

    public function store(Request $request): CompensationPackageResource
    {
        $this->authorizeManage($request);

        return new CompensationPackageResource(CompensationPackage::create($this->validated($request)));
    }

    public function update(Request $request, CompensationPackage $compensationPackage): CompensationPackageResource
    {
        $this->authorizeManage($request);

        $compensationPackage->update($this->validated($request, $compensationPackage));

        return new CompensationPackageResource($compensationPackage);
    }

    private function validated(Request $request, ?CompensationPackage $package = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('compensation_packages')->ignore($package)],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'amount_basis' => ['sometimes', Rule::in(array_keys(CompensationPackage::AMOUNT_BASES))],
            'payment_frequency' => ['sometimes', Rule::in(array_keys(CompensationPackage::PAYMENT_FREQUENCIES))],
            'type' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + [
            'amount_basis' => 'annual',
            'payment_frequency' => 'monthly',
            'is_active' => $request->boolean('is_active', true),
        ];

        $data['currency'] = SystemSetting::mainCurrency();

        return $data;
    }

    private function authorizeView(Request $request): void
    {
        abort_unless(
            $request->user()?->can(PermissionName::CompensationView->value)
            || $request->user()?->can(PermissionName::CompensationManage->value),
            403
        );
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::CompensationManage->value), 403);
    }
}
