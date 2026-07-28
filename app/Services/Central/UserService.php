<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Central\Role;
use App\Models\Central\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Central platform user administration.
 */
final class UserService
{
    /**
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
            )
            ->allowedIncludes(
                AllowedInclude::relationship('roles'),
            )
            ->defaultSort('-created_at')
            ->with('roles')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, email: string, password: string, role?: string}  $data
     *
     * @throws Throwable
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            /** @var User $user */
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'email_verified_at' => now(),
            ]);

            $user->assignRole($data['role'] ?? Role::Support->value);

            return $user->load('roles');
        });
    }

    public function find(User $user): User
    {
        return $user->loadMissing('roles');
    }

    /**
     * @param  array{name?: string, email?: string, password?: string, role?: string}  $data
     *
     * @throws Throwable
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            if (isset($data['name'])) {
                $user->name = $data['name'];
            }

            if (isset($data['email'])) {
                $user->email = $data['email'];
            }

            if (isset($data['password'])) {
                $user->password = $data['password'];
            }

            $user->save();

            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }

            return $user->refresh()->load('roles');
        });
    }

    public function delete(User $user, ?User $actor = null): void
    {
        if ($actor !== null && $actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own account.'],
            ]);
        }

        $user->delete();
    }
}
