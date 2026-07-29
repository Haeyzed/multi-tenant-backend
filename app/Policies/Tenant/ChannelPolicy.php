<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Channel;
use App\Models\Tenant\User;

class ChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ChannelsView->value);
    }

    public function view(User $user, Channel $channel): bool
    {
        return $user->can(Permission::ChannelsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ChannelsCreate->value);
    }

    public function update(User $user, Channel $channel): bool
    {
        return $user->can(Permission::ChannelsUpdate->value);
    }

    public function delete(User $user, Channel $channel): bool
    {
        return $user->can(Permission::ChannelsDelete->value);
    }
}
