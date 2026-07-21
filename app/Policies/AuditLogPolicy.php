<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AuditView->value);
    }
}
