<?php

namespace App\Http\Requests\Api;

use App\Enums\PtoAccrualType;
use App\Models\PtoPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PtoPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('pto_policies', 'name')->ignore($this->route('ptoPolicy'))],
            'annual_allowance_days' => ['required', 'numeric', 'min:0', 'max:365', 'multiple_of:0.5'],
            'accrual_type' => ['required', Rule::enum(PtoAccrualType::class)],
            'accumulation_frequency' => ['required', Rule::in(array_keys(PtoPolicy::ACCUMULATION_FREQUENCIES))],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', Rule::in(array_keys(PtoPolicy::WEEKDAYS))],
            'holidays' => ['nullable', 'array'],
            'holidays.*' => ['required', 'date_format:Y-m-d'],
            'allow_negative_balance' => ['sometimes', 'boolean'],
            'carryover_days' => ['required', 'numeric', 'min:0', 'max:365', 'multiple_of:0.5'],
            'approval_strategy' => ['required', 'string', Rule::in(['manager_then_hr', 'manager_only', 'hr_only'])],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
