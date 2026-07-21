<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\AssetEvent;
use App\Models\User;

class AssetEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AssetsView->value) || $user->can(PermissionName::AssetsManage->value);
    }

    public function view(User $user, AssetEvent $assetEvent): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::AssetsManage->value);
    }
}
