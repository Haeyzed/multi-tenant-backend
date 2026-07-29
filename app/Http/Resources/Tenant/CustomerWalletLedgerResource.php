<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\CustomerWalletLedger;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read CustomerWalletLedger $resource
 *
 * @mixin CustomerWalletLedger
 */
#[SchemaName('CustomerWalletLedger')]
class CustomerWalletLedgerResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_wallet_id' => $this->customer_wallet_id,
            'type' => $this->type->value,
            'amount' => $this->amount,
            'points' => $this->points,
            'balance_after' => $this->balance_after,
            'points_after' => $this->points_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
