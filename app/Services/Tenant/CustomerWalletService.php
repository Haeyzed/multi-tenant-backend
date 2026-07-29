<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\WalletLedgerType;
use App\Events\Tenant\Erp\WalletCredited;
use App\Events\Tenant\Erp\WalletDebited;
use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerWallet;
use App\Models\Tenant\CustomerWalletLedger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Customer wallet balance and loyalty points management.
 */
final class CustomerWalletService
{
    public function ensureWallet(Customer $customer): CustomerWallet
    {
        /** @var CustomerWallet|null $wallet */
        $wallet = CustomerWallet::query()->where('customer_id', $customer->id)->first();

        if ($wallet !== null) {
            return $wallet;
        }

        return CustomerWallet::query()->create([
            'customer_id' => $customer->id,
            'balance' => 0,
            'loyalty_points' => 0,
            'currency' => strtoupper($customer->currency ?? 'USD'),
        ]);
    }

    public function find(Customer $customer): CustomerWallet
    {
        return $this->ensureWallet($customer)->loadMissing(['customer', 'ledgers']);
    }

    /**
     * @return LengthAwarePaginator<int, CustomerWalletLedger>
     */
    public function listLedgers(Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        $wallet = $this->ensureWallet($customer);

        return $wallet->ledgers()
            ->latest('created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @throws Throwable
     */
    public function credit(
        Customer $customer,
        int $amount,
        ?string $notes = null,
        ?Model $reference = null,
    ): CustomerWalletLedger {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Credit amount must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($customer, $amount, $notes, $reference): CustomerWalletLedger {
            $wallet = CustomerWallet::query()->lockForUpdate()->firstOrCreate(
                ['customer_id' => $customer->id],
                [
                    'balance' => 0,
                    'loyalty_points' => 0,
                    'currency' => strtoupper($customer->currency ?? 'USD'),
                ],
            );

            $wallet->balance += $amount;
            $wallet->save();

            $ledger = CustomerWalletLedger::query()->create([
                'customer_wallet_id' => $wallet->id,
                'type' => WalletLedgerType::Credit,
                'amount' => $amount,
                'points' => 0,
                'balance_after' => $wallet->balance,
                'points_after' => $wallet->loyalty_points,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            $this->dispatchWalletCredited($ledger);

            return $ledger->loadMissing('wallet');
        });
    }

    /**
     * @throws Throwable
     */
    public function debit(
        Customer $customer,
        int $amount,
        ?string $notes = null,
        ?Model $reference = null,
    ): CustomerWalletLedger {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Debit amount must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($customer, $amount, $notes, $reference): CustomerWalletLedger {
            $wallet = CustomerWallet::query()->lockForUpdate()->firstOrCreate(
                ['customer_id' => $customer->id],
                [
                    'balance' => 0,
                    'loyalty_points' => 0,
                    'currency' => strtoupper($customer->currency ?? 'USD'),
                ],
            );

            if ($wallet->balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient wallet balance.'],
                ]);
            }

            $wallet->balance -= $amount;
            $wallet->save();

            $ledger = CustomerWalletLedger::query()->create([
                'customer_wallet_id' => $wallet->id,
                'type' => WalletLedgerType::Debit,
                'amount' => $amount,
                'points' => 0,
                'balance_after' => $wallet->balance,
                'points_after' => $wallet->loyalty_points,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            $this->dispatchWalletDebited($ledger);

            return $ledger->loadMissing('wallet');
        });
    }

    /**
     * @throws Throwable
     */
    public function earnPoints(
        Customer $customer,
        int $points,
        ?string $notes = null,
        ?Model $reference = null,
    ): CustomerWalletLedger {
        if ($points <= 0) {
            throw ValidationException::withMessages([
                'points' => ['Points earned must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($customer, $points, $notes, $reference): CustomerWalletLedger {
            $wallet = CustomerWallet::query()->lockForUpdate()->firstOrCreate(
                ['customer_id' => $customer->id],
                [
                    'balance' => 0,
                    'loyalty_points' => 0,
                    'currency' => strtoupper($customer->currency ?? 'USD'),
                ],
            );

            $wallet->loyalty_points += $points;
            $wallet->save();

            return CustomerWalletLedger::query()->create([
                'customer_wallet_id' => $wallet->id,
                'type' => WalletLedgerType::LoyaltyEarn,
                'amount' => 0,
                'points' => $points,
                'balance_after' => $wallet->balance,
                'points_after' => $wallet->loyalty_points,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * @throws Throwable
     */
    public function redeemPoints(
        Customer $customer,
        int $points,
        ?string $notes = null,
        ?Model $reference = null,
    ): CustomerWalletLedger {
        if ($points <= 0) {
            throw ValidationException::withMessages([
                'points' => ['Points to redeem must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($customer, $points, $notes, $reference): CustomerWalletLedger {
            $wallet = CustomerWallet::query()->lockForUpdate()->firstOrCreate(
                ['customer_id' => $customer->id],
                [
                    'balance' => 0,
                    'loyalty_points' => 0,
                    'currency' => strtoupper($customer->currency ?? 'USD'),
                ],
            );

            if ($wallet->loyalty_points < $points) {
                throw ValidationException::withMessages([
                    'points' => ['Insufficient loyalty points.'],
                ]);
            }

            $wallet->loyalty_points -= $points;
            $wallet->save();

            return CustomerWalletLedger::query()->create([
                'customer_wallet_id' => $wallet->id,
                'type' => WalletLedgerType::LoyaltyRedeem,
                'amount' => 0,
                'points' => $points,
                'balance_after' => $wallet->balance,
                'points_after' => $wallet->loyalty_points,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);
        });
    }

    private function dispatchWalletCredited(CustomerWalletLedger $ledger): void
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        event(new WalletCredited($ledger, (string) $tenant->getTenantKey()));
    }

    private function dispatchWalletDebited(CustomerWalletLedger $ledger): void
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        event(new WalletDebited($ledger, (string) $tenant->getTenantKey()));
    }
}
