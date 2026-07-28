<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\BusinessSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant business settings key/value store.
 */
final class BusinessSettingService
{
    /**
     * @return LengthAwarePaginator<int, BusinessSetting>
     */
    public function list(int $perPage = 50): LengthAwarePaginator
    {
        return QueryBuilder::for(BusinessSetting::class)
            ->allowedFilters(
                AllowedFilter::exact('key'),
                AllowedFilter::exact('group'),
                AllowedFilter::exact('type'),
            )
            ->allowedSorts(
                AllowedSort::field('key'),
                AllowedSort::field('group'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('group')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    public function findByKey(string $key): BusinessSetting
    {
        return BusinessSetting::query()->where('key', $key)->firstOrFail();
    }

    /**
     * @param  array{key: string, value?: mixed, type?: string, group?: string, description?: string|null}  $data
     */
    public function upsert(array $data): BusinessSetting
    {
        /** @var BusinessSetting $setting */
        $setting = BusinessSetting::query()->firstOrNew(['key' => $data['key']]);

        if (isset($data['type'])) {
            $setting->type = $data['type'];
        } elseif (! $setting->exists) {
            $setting->type = 'string';
        }

        if (isset($data['group'])) {
            $setting->group = $data['group'];
        } elseif (! $setting->exists) {
            $setting->group = 'general';
        }

        if (array_key_exists('description', $data)) {
            $setting->description = $data['description'];
        }

        if (array_key_exists('value', $data)) {
            $setting->value = $this->encodeValue($data['value'], $setting->type ?: 'string');
        }

        $setting->save();

        return $setting->refresh();
    }

    public function delete(BusinessSetting $setting): void
    {
        $setting->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function map(): array
    {
        return BusinessSetting::query()
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (BusinessSetting $setting): array => [$setting->key => $setting->decodedValue()])
            ->all();
    }

    private function encodeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'integer' => (string) (int) $value,
            'json' => is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }
}
