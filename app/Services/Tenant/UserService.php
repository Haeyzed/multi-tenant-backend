<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\Role;
use App\Models\Tenant;
use App\Models\Tenant\User;
use App\Services\Billing\EntitlementEnforcer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Tenant-scoped user management against the current tenant database.
 *
 * Provides listing via Spatie Query Builder, create/update of user profiles
 * (including Spatie role assignment), and deletion with self-delete protection.
 */
final class UserService
{
    public function __construct(private EntitlementEnforcer $entitlements) {}

    /**
     * List users with filters, sorts, sparse fields, and includes from the request.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(User::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('email'),
                AllowedSort::field('created_at'),
                AllowedSort::field('updated_at'),
            )
            ->allowedFields(
                'id',
                'name',
                'email',
                'email_verified_at',
                'created_at',
                'updated_at',
            )
            ->allowedIncludes(
                AllowedInclude::relationship('roles'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * Create a user and assign a Spatie role in the current tenant database.
     *
     * Defaults to {@see Role::Member} when no role is provided. Admin-created
     * users are marked email-verified.
     *
     * @param  array{name: string, email: string, password: string, role?: string}  $data
     *
     * @throws Throwable
     */
    public function create(array $data): User
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $this->entitlements->assertCanCreateUser($tenant);

        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'email_verified_at' => now(),
            ]);

            $user->assignRole($data['role'] ?? Role::Member->value);

            return $user->load('roles');
        });
    }

    /**
     * Return the given user with roles for API presentation.
     */
    public function find(User $user): User
    {
        return $user->loadMissing('roles');
    }

    /**
     * Update mutable user attributes and optionally sync their role.
     *
     * @param  array{name?: string, email?: string, password?: string, role?: string}  $data
     *
     * @throws Throwable
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $payload = [];

            if (array_key_exists('name', $data)) {
                $payload['name'] = $data['name'];
            }

            if (array_key_exists('email', $data)) {
                $payload['email'] = $data['email'];
            }

            if (array_key_exists('password', $data) && $data['password'] !== null) {
                $payload['password'] = $data['password'];
            }

            if ($payload !== []) {
                $user->update($payload);
            }

            if (array_key_exists('role', $data) && $data['role'] !== null) {
                $user->syncRoles([$data['role']]);
            }

            return $user->refresh()->load('roles');
        });
    }

    /**
     * Delete a user after revoking their Sanctum tokens.
     *
     * @throws ValidationException When `$user` is the same model as `$actor`.
     */
    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own account.'],
            ]);
        }

        $user->tokens()->delete();
        $user->delete();
    }
}
