<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::EmployeesView->value) || $user->can(PermissionName::EmployeesManage->value);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->viewAny($user)
            || $employee->user_id === $user->id
            || $employee->manager?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::EmployeesManage->value);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can(PermissionName::EmployeesManage->value);
    }

    public function viewCompensation(User $user, Employee $employee): bool
    {
        return $user->can(PermissionName::CompensationView->value) || $user->can(PermissionName::CompensationManage->value);
    }

    public function manageCompensation(User $user, Employee $employee): bool
    {
        return $user->can(PermissionName::CompensationManage->value);
    }

    public function viewBenefits(User $user, Employee $employee): bool
    {
        return $user->can(PermissionName::BenefitsView->value) || $user->can(PermissionName::BenefitsManage->value);
    }

    public function manageBenefits(User $user, Employee $employee): bool
    {
        return $user->can(PermissionName::BenefitsManage->value);
    }
}
