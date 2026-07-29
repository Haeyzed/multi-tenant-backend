<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\Resource;
use App\Models\Central\PlanFeature;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read PlanFeature $resource
 *
 * @mixin PlanFeature
 */
#[SchemaName('PlanFeature')]
class PlanFeatureResource extends Resource
{
    /**
     * @return array{id: int, feature_key: string, value: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'feature_key' => $this->feature_key,
            'value' => $this->value,
        ];
    }
}
