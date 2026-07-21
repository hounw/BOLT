<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'manager_id' => $this->manager_id,
            'employee_number' => $this->employee_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'work_email' => $this->work_email,
            'personal_email' => $this->when($request->user()?->can('employees.manage'), $this->personal_email),
            'phone' => $this->when($request->user()?->can('employees.manage'), $this->phone),
            'status' => $this->status?->value,
            'department_id' => $this->department_id,
            'department' => $this->department,
            'position_id' => $this->position_id,
            'title' => $this->title,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'emergency_contact' => $this->when($request->user()?->can('employees.manage'), $this->emergency_contact),
            'hr_metadata' => $this->when($request->user()?->can('employees.manage'), $this->hr_metadata),
            'private_hr_data' => $this->when($request->user()?->can('employees.manage'), $this->private_hr_data),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
