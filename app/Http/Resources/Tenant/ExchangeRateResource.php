<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ExchangeRate;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ExchangeRate $resource
 *
 * @mixin ExchangeRate
 */
#[SchemaName('ExchangeRate')]
class ExchangeRateResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency_from' => $this->currency_from,
            'currency_to' => $this->currency_to,
            'rate' => $this->rate,
            'effective_at' => $this->effective_at,
            'source' => $this->source,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
