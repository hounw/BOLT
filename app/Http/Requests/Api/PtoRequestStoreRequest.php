<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PtoRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'pto_policy_id' => ['required', 'integer', 'exists:pto_policies,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'days' => ['required', 'numeric', 'min:0.5', 'max:365', 'multiple_of:0.5'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
