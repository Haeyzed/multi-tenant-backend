<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\GoodsReceipt;
use App\Models\Tenant\User;

class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::GoodsReceiptsView->value);
    }

    public function view(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->can(Permission::GoodsReceiptsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::GoodsReceiptsCreate->value);
    }

    public function update(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->can(Permission::GoodsReceiptsUpdate->value);
    }

    public function delete(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->can(Permission::GoodsReceiptsDelete->value);
    }

    public function post(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->can(Permission::GoodsReceiptsUpdate->value);
    }

    public function cancel(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->can(Permission::GoodsReceiptsUpdate->value);
    }
}
