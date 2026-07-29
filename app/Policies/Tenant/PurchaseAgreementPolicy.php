<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\PurchaseAgreement;
use App\Models\Tenant\User;

class PurchaseAgreementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PurchaseAgreementsView->value);
    }

    public function view(User $user, PurchaseAgreement $purchaseAgreement): bool
    {
        return $user->can(Permission::PurchaseAgreementsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PurchaseAgreementsCreate->value);
    }

    public function update(User $user, PurchaseAgreement $purchaseAgreement): bool
    {
        return $user->can(Permission::PurchaseAgreementsUpdate->value);
    }

    public function delete(User $user, PurchaseAgreement $purchaseAgreement): bool
    {
        return $user->can(Permission::PurchaseAgreementsDelete->value);
    }

    public function activate(User $user, PurchaseAgreement $purchaseAgreement): bool
    {
        return $user->can(Permission::PurchaseAgreementsUpdate->value);
    }

    public function cancel(User $user, PurchaseAgreement $purchaseAgreement): bool
    {
        return $user->can(Permission::PurchaseAgreementsUpdate->value);
    }
}
