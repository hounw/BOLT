<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\PtoPolicy;
use App\Models\User;

class PtoPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PtoView->value) || $user->can(PermissionName::PtoManage->value);
    }

    public function view(User $user, PtoPolicy $ptoPolicy): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PtoManage->value);
    }

    public function update(User $user, PtoPolicy $ptoPolicy): bool
    {
        return $user->can(PermissionName::PtoManage->value);
    }
}
