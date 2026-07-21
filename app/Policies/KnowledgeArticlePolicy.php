<?php

namespace App\Policies;

use App\Enums\KnowledgeArticleStatus;
use App\Enums\PermissionName;
use App\Models\KnowledgeArticle;
use App\Models\User;

class KnowledgeArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::KnowledgeView->value) || $user->can(PermissionName::KnowledgeManage->value);
    }

    public function view(User $user, KnowledgeArticle $knowledgeArticle): bool
    {
        return $user->can(PermissionName::KnowledgeManage->value)
            || ($user->can(PermissionName::KnowledgeView->value)
                && $knowledgeArticle->status === KnowledgeArticleStatus::Published);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::KnowledgeManage->value);
    }

    public function update(User $user, KnowledgeArticle $knowledgeArticle): bool
    {
        return $user->can(PermissionName::KnowledgeManage->value);
    }
}
