<?php

namespace App\Http\Requests\Api;

use App\Enums\EmployeeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employee = $this->route('employee');

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employee)],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('employees')->ignore($employee)],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'work_email' => ['nullable', 'email', 'max:255', Rule::unique('employees')->ignore($employee)],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::enum(EmployeeStatus::class)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'department' => ['nullable', 'string', 'max:120'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'title' => ['nullable', 'string', 'max:120'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'emergency_contact' => ['nullable', 'array'],
            'hr_metadata' => ['nullable', 'array'],
            'private_hr_data' => ['nullable', 'array'],
            'compensation_package_id' => ['nullable', 'integer', 'exists:compensation_packages,id'],
            'compensation_effective_date' => ['nullable', 'date', 'required_with:compensation_package_id'],
            'starting_pto_policy_id' => ['nullable', 'integer', 'exists:pto_policies,id'],
            'starting_pto_available_days' => ['nullable', 'numeric', 'min:0', 'multiple_of:0.5', 'required_with:starting_pto_policy_id'],
            'starting_pto_period_start' => ['nullable', 'date', 'required_with:starting_pto_policy_id'],
            'starting_pto_period_end' => ['nullable', 'date', 'after_or_equal:starting_pto_period_start', 'required_with:starting_pto_policy_id'],
        ];
    }
}
