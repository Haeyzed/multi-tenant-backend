<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Billing\FeatureKey;
use App\Enums\Billing\PlanInterval;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $currency = strtoupper((string) config('billing.default_currency', 'USD'));
        $secondaryCurrency = $currency === 'USD' ? 'EUR' : 'USD';

        $catalog = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Getting started plan.',
                'trial_days' => 0,
                'sort_order' => 0,
                'amount' => 0,
                'features' => [
                    FeatureKey::UsersMax->value => '3',
                    FeatureKey::DomainsMax->value => '1',
                    FeatureKey::ProductsMax->value => '25',
                    FeatureKey::OrdersMax->value => '100',
                    FeatureKey::CustomersMax->value => '50',
                    FeatureKey::EmployeesMax->value => '3',
                    FeatureKey::WarehousesMax->value => '1',
                    FeatureKey::StorageMb->value => '512',
                    FeatureKey::ApiRequestsPerMinute->value => '60',
                ],
            ],
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'description' => 'For small teams.',
                'trial_days' => 14,
                'sort_order' => 10,
                'amount' => 2900,
                'features' => [
                    FeatureKey::UsersMax->value => '10',
                    FeatureKey::DomainsMax->value => '3',
                    FeatureKey::ProductsMax->value => '250',
                    FeatureKey::OrdersMax->value => '1000',
                    FeatureKey::CustomersMax->value => '500',
                    FeatureKey::EmployeesMax->value => '10',
                    FeatureKey::WarehousesMax->value => '3',
                    FeatureKey::StorageMb->value => '5120',
                    FeatureKey::ApiRequestsPerMinute->value => '120',
                ],
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'description' => 'For growing businesses.',
                'trial_days' => 14,
                'sort_order' => 20,
                'amount' => 9900,
                'features' => [
                    FeatureKey::UsersMax->value => 'unlimited',
                    FeatureKey::DomainsMax->value => '10',
                    FeatureKey::ProductsMax->value => 'unlimited',
                    FeatureKey::OrdersMax->value => 'unlimited',
                    FeatureKey::CustomersMax->value => 'unlimited',
                    FeatureKey::EmployeesMax->value => 'unlimited',
                    FeatureKey::WarehousesMax->value => 'unlimited',
                    FeatureKey::StorageMb->value => '51200',
                    FeatureKey::ApiRequestsPerMinute->value => '600',
                ],
            ],
        ];

        foreach ($catalog as $item) {
            /** @var Plan $plan */
            $plan = Plan::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'is_active' => true,
                    'trial_days' => $item['trial_days'],
                    'sort_order' => $item['sort_order'],
                ],
            );

            $plan->prices()->updateOrCreate(
                [
                    'currency' => $currency,
                    'interval' => PlanInterval::Month->value,
                    'interval_count' => 1,
                ],
                [
                    'amount' => $item['amount'],
                    'is_active' => true,
                ],
            );

            if ($item['amount'] > 0) {
                $paidIntervals = [
                    [PlanInterval::Quarter, (int) round($item['amount'] * 2.7)],
                    [PlanInterval::SemiAnnual, (int) round($item['amount'] * 5.2)],
                    [PlanInterval::Year, $item['amount'] * 10],
                    [PlanInterval::Lifetime, $item['amount'] * 50],
                ];

                foreach ($paidIntervals as [$interval, $amount]) {
                    $plan->prices()->updateOrCreate(
                        [
                            'currency' => $currency,
                            'interval' => $interval->value,
                            'interval_count' => 1,
                        ],
                        [
                            'amount' => $amount,
                            'is_active' => true,
                        ],
                    );
                }

                $plan->prices()->updateOrCreate(
                    [
                        'currency' => $secondaryCurrency,
                        'interval' => PlanInterval::Month->value,
                        'interval_count' => 1,
                    ],
                    [
                        'amount' => (int) round($item['amount'] * 0.92),
                        'is_active' => true,
                    ],
                );
            }

            foreach ($item['features'] as $key => $value) {
                $plan->features()->updateOrCreate(
                    ['feature_key' => $key],
                    ['value' => $value],
                );
            }
        }
    }
}
