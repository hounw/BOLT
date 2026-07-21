<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AttachmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::FilesView->value) || $user->can(PermissionName::FilesManage->value);
    }

    public function view(User $user, Attachment $attachment): bool
    {
        if ($attachment->attachable instanceof KnowledgeArticle) {
            return ($user->can(PermissionName::FilesView->value) || $user->can(PermissionName::FilesManage->value))
                && Gate::forUser($user)->allows('view', $attachment->attachable);
        }

        if ($user->can(PermissionName::FilesManage->value)) {
            return true;
        }

        if (! $user->can(PermissionName::FilesView->value)) {
            return false;
        }

        return match (true) {
            $attachment->attachable instanceof Employee => $this->canViewEmployeeFile($user, $attachment->attachable),
            $attachment->attachable instanceof Asset => $user->can(PermissionName::AssetsView->value)
                || $user->can(PermissionName::AssetsManage->value),
            $attachment->attachable instanceof AssetEvent => $user->can(PermissionName::AssetsView->value)
                || $user->can(PermissionName::AssetsManage->value),
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::FilesManage->value);
    }

    private function canViewEmployeeFile(User $user, Employee $employee): bool
    {
        return $user->can(PermissionName::EmployeesManage->value)
            || $employee->user_id === $user->id
            || $employee->manager?->user_id === $user->id;
    }
}
