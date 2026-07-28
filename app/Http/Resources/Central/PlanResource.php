<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\Resource;
use App\Models\Plan;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Plan $resource
 *
 * @mixin Plan
 */
#[SchemaName('Plan')]
class PlanResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'trial_days' => $this->trial_days,
            'sort_order' => $this->sort_order,
            'prices' => PlanPriceResource::collection($this->whenLoaded('prices')),
            'features' => PlanFeatureResource::collection($this->whenLoaded('features')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
