<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\KnowledgeCategory;
use App\Models\User;

class KnowledgeCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::KnowledgeView->value) || $user->can(PermissionName::KnowledgeManage->value);
    }

    public function view(User $user, KnowledgeCategory $knowledgeCategory): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::KnowledgeManage->value);
    }

    public function update(User $user, KnowledgeCategory $knowledgeCategory): bool
    {
        return $user->can(PermissionName::KnowledgeManage->value);
    }

    public function delete(User $user, KnowledgeCategory $knowledgeCategory): bool
    {
        return $user->can(PermissionName::KnowledgeManage->value);
    }
}
