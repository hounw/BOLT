<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::WebhooksManage->value);
    }

    public function view(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $user->can(PermissionName::WebhooksManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::WebhooksManage->value);
    }

    public function update(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $user->can(PermissionName::WebhooksManage->value);
    }

    public function delete(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $user->can(PermissionName::WebhooksManage->value);
    }
}
