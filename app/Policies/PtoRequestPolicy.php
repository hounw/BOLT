<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\PtoRequestStatus;
use App\Models\PtoRequest;
use App\Models\User;

class PtoRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PtoView->value) || $user->can(PermissionName::PtoManage->value);
    }

    public function view(User $user, PtoRequest $ptoRequest): bool
    {
        return $this->viewAny($user)
            || $ptoRequest->employee?->user_id === $user->id
            || $ptoRequest->approver_id === $user->id
            || $ptoRequest->employee?->manager?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PtoView->value) || $user->can(PermissionName::PtoManage->value);
    }

    public function approve(User $user, PtoRequest $ptoRequest): bool
    {
        if ($ptoRequest->status !== PtoRequestStatus::Pending) {
            return false;
        }

        if ($user->can(PermissionName::PtoManage->value)) {
            return true;
        }

        if (! $user->can(PermissionName::PtoApprove->value)) {
            return false;
        }

        $ptoRequest->loadMissing(['employee.manager', 'policy']);

        if ($ptoRequest->policy?->approval_strategy === 'hr_only') {
            return false;
        }

        return $ptoRequest->employee?->manager?->user_id === $user->id;
    }

    public function cancel(User $user, PtoRequest $ptoRequest): bool
    {
        return $ptoRequest->status === PtoRequestStatus::Pending
            && ($user->can(PermissionName::PtoManage->value) || $ptoRequest->employee?->user_id === $user->id);
    }
}
