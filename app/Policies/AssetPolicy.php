<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AssetsView->value) || $user->can(PermissionName::AssetsManage->value);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::AssetsManage->value);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->can(PermissionName::AssetsManage->value);
    }

    public function assign(User $user, Asset $asset): bool
    {
        return $user->can(PermissionName::AssetsManage->value);
    }
}
