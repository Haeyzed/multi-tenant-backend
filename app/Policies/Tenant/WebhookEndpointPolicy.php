<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\User;
use App\Models\Tenant\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::WebhooksView->value);
    }

    public function view(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $user->can(Permission::WebhooksView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::WebhooksCreate->value);
    }

    public function update(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $user->can(Permission::WebhooksUpdate->value);
    }

    public function delete(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $user->can(Permission::WebhooksDelete->value);
    }
}
