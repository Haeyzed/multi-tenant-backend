<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\PriceListAssignmentType;
use App\Enums\Tenant\PromotionType;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerGroup;
use App\Models\Tenant\PriceList;
use App\Models\Tenant\PriceListAssignment;
use App\Models\Tenant\PriceListItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\Promotion;
use Illuminate\Support\Carbon;

/**
 * Resolves unit prices from price lists, group discounts, and promotions.
 *
 * @phpstan-type PriceQuote array{
 *     product_id: int,
 *     quantity: int,
 *     currency: string,
 *     catalog_unit_price: int,
 *     list_unit_price: int|null,
 *     group_discount_percent: int,
 *     unit_price_before_promotion: int,
 *     unit_price: int,
 *     line_total: int,
 *     discount_amount: int,
 *     price_list_id: int|null,
 *     promotion_id: int|null,
 *     promotion_code: string|null
 * }
 */
final class PricingEngine
{
    public function __construct(private ChannelPricingService $channelPricing) {}

    /**
     * @return PriceQuote
     */
    public function quote(
        Product $product,
        int $quantity = 1,
        ?Customer $customer = null,
        ?int $priceListId = null,
        ?int $channelId = null,
        ?Carbon $at = null,
    ): array {
        $quantity = max(1, $quantity);
        $at ??= now();
        $customer?->loadMissing('group');

        $catalog = (int) $product->unit_price;
        $channelOverride = $channelId !== null
            ? $this->channelPricing->resolveUnitPrice($channelId, $product->id, $quantity, $product->currency)
            : null;
        $priceList = $this->resolvePriceList($product->currency, $customer, $priceListId, $channelId, $at);
        $listUnit = $priceList !== null
            ? $this->resolveListUnitPrice($priceList, $product->id, $quantity)
            : null;

        $unit = $channelOverride ?? $listUnit ?? $catalog;
        $groupDiscountPercent = (int) ($customer?->group?->discount_percent ?? 0);

        if ($channelOverride === null && $listUnit === null && $groupDiscountPercent > 0) {
            $unit = (int) round($unit * (100 - min(100, $groupDiscountPercent)) / 100);
        }

        $beforePromotion = $unit;
        $promotion = $this->resolvePromotion($product, $customer, $unit * $quantity, $product->currency, $at);
        $promotionId = null;
        $promotionCode = null;

        if ($promotion !== null) {
            $unit = $this->applyPromotion($unit, $promotion);
            $promotionId = $promotion->id;
            $promotionCode = $promotion->code;
        }

        $lineTotal = $unit * $quantity;
        $discountAmount = max(0, ($catalog * $quantity) - $lineTotal);

        return [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'currency' => $product->currency,
            'catalog_unit_price' => $catalog,
            'list_unit_price' => $listUnit,
            'group_discount_percent' => $groupDiscountPercent,
            'unit_price_before_promotion' => $beforePromotion,
            'unit_price' => $unit,
            'line_total' => $lineTotal,
            'discount_amount' => $discountAmount,
            'price_list_id' => $priceList?->id,
            'promotion_id' => $promotionId,
            'promotion_code' => $promotionCode,
        ];
    }

    private function resolvePriceList(
        string $currency,
        ?Customer $customer,
        ?int $priceListId,
        ?int $channelId,
        Carbon $at,
    ): ?PriceList {
        if ($priceListId !== null) {
            return PriceList::query()
                ->currentlyEffective($at)
                ->whereKey($priceListId)
                ->where('currency', $currency)
                ->first();
        }

        if ($customer !== null) {
            $assigned = $this->findAssignedList(PriceListAssignmentType::Customer, $customer->id, $currency, $at);

            if ($assigned !== null) {
                return $assigned;
            }

            if ($customer->customer_group_id !== null) {
                $groupAssigned = $this->findAssignedList(
                    PriceListAssignmentType::CustomerGroup,
                    $customer->customer_group_id,
                    $currency,
                    $at,
                );

                if ($groupAssigned !== null) {
                    return $groupAssigned;
                }

                $group = $customer->group ?? CustomerGroup::query()->find($customer->customer_group_id);

                if ($group?->price_list_id !== null) {
                    $fromGroup = PriceList::query()
                        ->currentlyEffective($at)
                        ->whereKey($group->price_list_id)
                        ->where('currency', $currency)
                        ->first();

                    if ($fromGroup !== null) {
                        return $fromGroup;
                    }
                }
            }
        }

        if ($channelId !== null) {
            $channelList = $this->findAssignedList(PriceListAssignmentType::Channel, $channelId, $currency, $at);

            if ($channelList !== null) {
                return $channelList;
            }
        }

        return PriceList::query()
            ->currentlyEffective($at)
            ->where('currency', $currency)
            ->where('is_default', true)
            ->orderByDesc('priority')
            ->first();
    }

    private function findAssignedList(
        PriceListAssignmentType $type,
        int $assignableId,
        string $currency,
        Carbon $at,
    ): ?PriceList {
        $assignment = PriceListAssignment::query()
            ->where('assignable_type', $type)
            ->where('assignable_id', $assignableId)
            ->whereHas('priceList', function ($query) use ($currency, $at): void {
                $query->currentlyEffective($at)->where('currency', $currency);
            })
            ->with(['priceList' => fn ($q) => $q->currentlyEffective($at)->where('currency', $currency)])
            ->get()
            ->sortByDesc(fn (PriceListAssignment $row): int => $row->priceList?->priority ?? 0)
            ->first();

        return $assignment?->priceList;
    }

    private function resolveListUnitPrice(PriceList $priceList, int $productId, int $quantity): ?int
    {
        $item = PriceListItem::query()
            ->where('price_list_id', $priceList->id)
            ->where('product_id', $productId)
            ->where('min_quantity', '<=', $quantity)
            ->orderByDesc('min_quantity')
            ->first();

        return $item?->unit_price;
    }

    private function resolvePromotion(
        Product $product,
        ?Customer $customer,
        int $lineSubtotal,
        string $currency,
        Carbon $at,
    ): ?Promotion {
        $promotions = Promotion::query()
            ->currentlyEffective($at)
            ->with(['products:id', 'customerGroups:id'])
            ->orderByDesc('priority')
            ->orderByDesc('value')
            ->get();

        foreach ($promotions as $promotion) {
            if ($promotion->currency !== null && $promotion->currency !== $currency) {
                continue;
            }

            if ($promotion->min_subtotal !== null && $lineSubtotal < $promotion->min_subtotal) {
                continue;
            }

            if ($promotion->products->isNotEmpty() && ! $promotion->products->contains('id', $product->id)) {
                continue;
            }

            if ($promotion->customerGroups->isNotEmpty()) {
                $groupId = $customer?->customer_group_id;

                if ($groupId === null || ! $promotion->customerGroups->contains('id', $groupId)) {
                    continue;
                }
            }

            return $promotion;
        }

        return null;
    }

    private function applyPromotion(int $unitPrice, Promotion $promotion): int
    {
        return match ($promotion->type) {
            PromotionType::PercentOff => (int) round($unitPrice * (100 - min(100, $promotion->value)) / 100),
            PromotionType::FixedAmount => max(0, $unitPrice - $promotion->value),
        };
    }
}
