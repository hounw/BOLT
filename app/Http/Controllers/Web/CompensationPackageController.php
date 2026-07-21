<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\CompensationPackage;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompensationPackageController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return view('web.compensation-packages.index', [
            'mainCurrency' => SystemSetting::mainCurrency(),
            'mainCurrencySymbol' => SystemSetting::mainCurrencySymbol(),
            'packages' => CompensationPackage::query()
                ->search($filters['q'] ?? null)
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeManage($request);

        $package = CompensationPackage::create($this->validated($request));

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $this->packagePayload($package),
            ], 201);
        }

        return redirect()->route('compensation-packages.index')->with('status', 'Compensation package created.');
    }

    public function update(Request $request, CompensationPackage $compensationPackage): RedirectResponse
    {
        $this->authorizeManage($request);

        $compensationPackage->update($this->validated($request, $compensationPackage));

        return redirect()->route('compensation-packages.index')->with('status', 'Compensation package updated.');
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

    private function packagePayload(CompensationPackage $package): array
    {
        return [
            'id' => $package->id,
            'name' => $package->name,
            'amount' => $package->amount,
            'currency' => $package->currency,
            'amount_basis' => $package->amount_basis,
            'payment_frequency' => $package->payment_frequency,
            'type' => $package->type,
            'option_label' => $package->optionLabel(),
        ];
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
