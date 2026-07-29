<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\GiftCardStatus;
use App\Events\Tenant\Erp\GiftCardRedeemed;
use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\GiftCard;
use App\Models\Tenant\GiftCardRedemption;
use App\Models\Tenant\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Gift card issuance, balance checks, and redemption.
 */
final class GiftCardService
{
    /**
     * @return LengthAwarePaginator<int, GiftCard>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(GiftCard::class)
            ->with(['customer', 'creator'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('issued_to'),
                AllowedFilter::partial('code'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('code'),
                AllowedSort::field('status'),
                AllowedSort::field('balance_remaining'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     amount: int,
     *     currency?: string,
     *     pin?: string|null,
     *     issued_to?: int|null,
     *     expires_at?: string|null,
     *     notes?: string|null,
     *     code?: string|null
     * }  $data
     *
     * @throws Throwable
     */
    public function issue(array $data): GiftCard
    {
        $amount = (int) $data['amount'];

        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => ['Gift card amount must be at least 1.'],
            ]);
        }

        if (isset($data['issued_to']) && $data['issued_to'] !== null) {
            if (! Customer::query()->whereKey($data['issued_to'])->exists()) {
                throw ValidationException::withMessages([
                    'issued_to' => ['The selected customer is invalid.'],
                ]);
            }
        }

        $code = $data['code'] ?? $this->generateCode();

        if (GiftCard::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => ['The gift card code has already been taken.'],
            ]);
        }

        /** @var GiftCard $giftCard */
        $giftCard = GiftCard::query()->create([
            'code' => $code,
            'pin' => $data['pin'] ?? null,
            'balance_initial' => $amount,
            'balance_remaining' => $amount,
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'status' => GiftCardStatus::Active,
            'issued_to' => $data['issued_to'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return $this->find($giftCard);
    }

    public function find(GiftCard $giftCard): GiftCard
    {
        return $giftCard->loadMissing(['customer', 'creator', 'redemptions']);
    }

    public function checkBalance(string $code): GiftCard
    {
        /** @var GiftCard|null $giftCard */
        $giftCard = GiftCard::query()->where('code', $code)->first();

        if ($giftCard === null) {
            throw ValidationException::withMessages([
                'code' => ['Gift card not found.'],
            ]);
        }

        $this->assertRedeemable($giftCard);

        return $this->find($giftCard);
    }

    /**
     * @throws Throwable
     */
    public function redeem(string $code, int $amount, ?Order $order = null): GiftCardRedemption
    {
        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => ['Redemption amount must be at least 1.'],
            ]);
        }

        return DB::transaction(function () use ($code, $amount, $order): GiftCardRedemption {
            /** @var GiftCard|null $giftCard */
            $giftCard = GiftCard::query()->where('code', $code)->lockForUpdate()->first();

            if ($giftCard === null) {
                throw ValidationException::withMessages([
                    'code' => ['Gift card not found.'],
                ]);
            }

            $this->assertRedeemable($giftCard);

            if ($giftCard->balance_remaining < $amount) {
                throw ValidationException::withMessages([
                    'amount' => ["Insufficient gift card balance. Available: {$giftCard->balance_remaining}."],
                ]);
            }

            $balanceBefore = $giftCard->balance_remaining;
            $balanceAfter = $balanceBefore - $amount;

            $giftCard->balance_remaining = $balanceAfter;
            $giftCard->status = $balanceAfter === 0
                ? GiftCardStatus::Redeemed
                : GiftCardStatus::Active;
            $giftCard->save();

            /** @var GiftCardRedemption $redemption */
            $redemption = GiftCardRedemption::query()->create([
                'gift_card_id' => $giftCard->id,
                'order_id' => $order?->id,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'redeemed_by' => auth()->id(),
                'created_at' => now(),
            ]);

            /** @var Tenant $tenant */
            $tenant = tenant();
            event(new GiftCardRedeemed($redemption, (string) $tenant->getTenantKey()));

            return $redemption->loadMissing(['giftCard', 'order']);
        });
    }

    public function void(GiftCard $giftCard): GiftCard
    {
        if ($giftCard->status === GiftCardStatus::Void) {
            throw ValidationException::withMessages([
                'status' => ['Gift card is already void.'],
            ]);
        }

        if ($giftCard->status === GiftCardStatus::Redeemed) {
            throw ValidationException::withMessages([
                'status' => ['Fully redeemed gift cards cannot be voided.'],
            ]);
        }

        $giftCard->update(['status' => GiftCardStatus::Void]);

        return $this->find($giftCard->refresh());
    }

    public function delete(GiftCard $giftCard): void
    {
        if ($giftCard->redemptions()->exists()) {
            throw ValidationException::withMessages([
                'gift_card' => ['Gift cards with redemptions cannot be deleted.'],
            ]);
        }

        $giftCard->delete();
    }

    private function assertRedeemable(GiftCard $giftCard): void
    {
        if ($giftCard->status === GiftCardStatus::Void) {
            throw ValidationException::withMessages([
                'code' => ['Gift card is void.'],
            ]);
        }

        if ($giftCard->status === GiftCardStatus::Expired
            || ($giftCard->expires_at !== null && $giftCard->expires_at->isPast())
        ) {
            if ($giftCard->status !== GiftCardStatus::Expired) {
                $giftCard->update(['status' => GiftCardStatus::Expired]);
            }

            throw ValidationException::withMessages([
                'code' => ['Gift card has expired.'],
            ]);
        }

        if ($giftCard->status === GiftCardStatus::Redeemed || $giftCard->balance_remaining < 1) {
            throw ValidationException::withMessages([
                'code' => ['Gift card has no remaining balance.'],
            ]);
        }

        if ($giftCard->status !== GiftCardStatus::Active) {
            throw ValidationException::withMessages([
                'code' => ['Gift card is not active.'],
            ]);
        }
    }

    private function generateCode(): string
    {
        do {
            $code = 'GC-'.Str::upper(Str::random(12));
        } while (GiftCard::query()->where('code', $code)->exists());

        return $code;
    }
}
