<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::EmployeesView->value);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can(Permission::EmployeesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::EmployeesCreate->value);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can(Permission::EmployeesUpdate->value);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can(Permission::EmployeesDelete->value);
    }
}
