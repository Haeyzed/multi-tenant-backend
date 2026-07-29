<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Central\Tenant;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use App\Services\Billing\EntitlementEnforcer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant employee management.
 */
final class EmployeeService
{
    public function __construct(private EntitlementEnforcer $entitlements) {}

    /**
     * @return LengthAwarePaginator<int, Employee>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Employee::class)
            ->with('user')
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
                AllowedFilter::partial('job_title'),
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('email'),
                AllowedSort::field('job_title'),
                AllowedSort::field('hired_at'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{user_id?: int|null, name: string, email?: string|null, phone?: string|null, job_title?: string|null, hired_at?: string|null, is_active?: bool}  $data
     */
    public function create(array $data): Employee
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $this->entitlements->assertCanCreateEmployee($tenant);
        $this->assertUserExists($data['user_id'] ?? null);

        return Employee::query()->create([
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'hired_at' => $data['hired_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Load an employee with its linked user relation.
     */
    public function find(Employee $employee): Employee
    {
        return $employee->load('user');
    }

    /**
     * @param  array{user_id?: int|null, name?: string, email?: string|null, phone?: string|null, job_title?: string|null, hired_at?: string|null, is_active?: bool}  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        if (array_key_exists('user_id', $data)) {
            $this->assertUserExists($data['user_id']);
        }

        $employee->fill($data)->save();

        return $employee->refresh()->load('user');
    }

    /**
     * Delete an employee record.
     */
    public function delete(Employee $employee): void
    {
        $employee->delete();
    }

    /**
     * Ensure the given user id, when provided, references an existing user.
     *
     * @throws ModelNotFoundException if the user id does not exist
     */
    private function assertUserExists(?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        if (! User::query()->whereKey($userId)->exists()) {
            throw (new ModelNotFoundException)->setModel(User::class, [$userId]);
        }
    }
}
