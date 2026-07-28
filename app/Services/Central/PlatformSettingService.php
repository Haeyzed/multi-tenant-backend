<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Central platform settings key/value store.
 */
final class PlatformSettingService
{
    /**
     * @return LengthAwarePaginator<int, PlatformSetting>
     */
    public function list(int $perPage = 50): LengthAwarePaginator
    {
        return QueryBuilder::for(PlatformSetting::class)
            ->allowedFilters(
                AllowedFilter::exact('key'),
                AllowedFilter::exact('group'),
                AllowedFilter::exact('type'),
                AllowedFilter::partial('description'),
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

    public function findByKey(string $key): PlatformSetting
    {
        return PlatformSetting::query()->where('key', $key)->firstOrFail();
    }

    /**
     * @param  array{
     *     key: string,
     *     value?: mixed,
     *     type?: string,
     *     group?: string,
     *     description?: string|null
     * }  $data
     */
    public function upsert(array $data): PlatformSetting
    {
        /** @var PlatformSetting $setting */
        $setting = PlatformSetting::query()->firstOrNew(['key' => $data['key']]);

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

        Cache::forget($this->cacheKey($setting->key));

        return $setting->refresh();
    }

    public function delete(PlatformSetting $setting): void
    {
        Cache::forget($this->cacheKey($setting->key));
        $setting->delete();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever($this->cacheKey($key), function () use ($key, $default): mixed {
            $setting = PlatformSetting::query()->where('key', $key)->first();

            return $setting?->decodedValue() ?? $default;
        });
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

    private function cacheKey(string $key): string
    {
        return 'platform_setting:'.$key;
    }
}
