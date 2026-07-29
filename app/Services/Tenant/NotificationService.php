<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\User;
use App\Notifications\Tenant\TenantErpNotification;

/**
 * Dispatches in-app ERP notifications to tenant users.
 */
final class NotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyUser(User $user, string $title, string $body, array $data = []): void
    {
        $user->notify(new TenantErpNotification($title, $body, $data));
    }
}
