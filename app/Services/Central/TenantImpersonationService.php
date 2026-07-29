<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Tenant\Role as TenantRole;
use App\Models\Central\Tenant;
use App\Models\Central\User as CentralUser;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Issues short-lived tenant Sanctum tokens for platform support impersonation.
 */
final class TenantImpersonationService
{
    /**
     * @return array{
     *     token: string,
     *     token_type: string,
     *     expires_at: string,
     *     domain: string|null,
     *     user: array{id: int, name: string, email: string}
     * }
     */
    public function impersonate(
        Tenant $tenant,
        CentralUser $actor,
        ?int $tenantUserId = null,
        int $minutes = 60,
    ): array {
        $minutes = max(1, min(480, $minutes));

        $payload = $tenant->run(function () use ($tenantUserId, $actor, $minutes): array {
            $user = $this->resolveUser($tenantUserId);

            $expiresAt = now()->addMinutes($minutes);
            $accessToken = $user->createToken(
                'impersonation:'.$actor->getAuthIdentifier(),
                ['*'],
                $expiresAt,
            );

            return [
                'token' => $accessToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ];
        });

        $domain = $tenant->domains()->orderBy('id')->value('domain');

        activity()
            ->causedBy($actor)
            ->performedOn($tenant)
            ->event('tenant.impersonated')
            ->withProperties([
                'tenant_user_id' => $payload['user']['id'],
                'tenant_user_email' => $payload['user']['email'],
                'expires_at' => $payload['expires_at'],
                'domain' => $domain,
            ])
            ->log('tenant.impersonated');

        return array_merge($payload, [
            'domain' => is_string($domain) ? $domain : null,
        ]);
    }

    private function resolveUser(?int $tenantUserId): TenantUser
    {
        if ($tenantUserId !== null) {
            return TenantUser::query()->findOrFail($tenantUserId);
        }

        if (! SpatieRole::query()->where('name', TenantRole::Admin->value)->where('guard_name', 'tenant')->exists()) {
            throw ValidationException::withMessages([
                'user_id' => ['No tenant admin role is available to impersonate.'],
            ]);
        }

        /** @var TenantUser|null $admin */
        $admin = TenantUser::role(TenantRole::Admin->value)->orderBy('id')->first();

        if ($admin === null) {
            throw ValidationException::withMessages([
                'user_id' => ['No tenant admin user is available to impersonate.'],
            ]);
        }

        return $admin;
    }
}
