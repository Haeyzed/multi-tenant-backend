<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Tenant;

/**
 * Typed tenant store / business profile configuration.
 */
final class StoreConfigData
{
    /**
     * @param  array{
     *     name?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     timezone?: string|null,
     *     currency?: string|null,
     *     locale?: string|null,
     *     tax_inclusive?: bool|null,
     *     default_tax_id?: int|null,
     *     logo_url?: string|null,
     *     address?: string|null
     * }  $attributes
     */
    public function __construct(public readonly array $attributes = []) {}

    /**
     * @param  array<string, mixed>  $map
     */
    public static function fromSettingsMap(array $map): self
    {
        return new self([
            'name' => self::stringOrNull($map['store.name'] ?? null),
            'email' => self::stringOrNull($map['store.email'] ?? null),
            'phone' => self::stringOrNull($map['store.phone'] ?? null),
            'timezone' => self::stringOrNull($map['store.timezone'] ?? null),
            'currency' => self::stringOrNull($map['store.currency'] ?? null),
            'locale' => self::stringOrNull($map['store.locale'] ?? null),
            'tax_inclusive' => array_key_exists('store.tax_inclusive', $map)
                ? filter_var($map['store.tax_inclusive'], FILTER_VALIDATE_BOOLEAN)
                : null,
            'default_tax_id' => isset($map['store.default_tax_id']) && $map['store.default_tax_id'] !== '' && $map['store.default_tax_id'] !== null
                ? (int) $map['store.default_tax_id']
                : null,
            'logo_url' => self::stringOrNull($map['store.logo_url'] ?? null),
            'address' => self::stringOrNull($map['store.address'] ?? null),
        ]);
    }

    /**
     * @return array{
     *     name: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     timezone: string|null,
     *     currency: string|null,
     *     locale: string|null,
     *     tax_inclusive: bool|null,
     *     default_tax_id: int|null,
     *     logo_url: string|null,
     *     address: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->attributes['name'] ?? null,
            'email' => $this->attributes['email'] ?? null,
            'phone' => $this->attributes['phone'] ?? null,
            'timezone' => $this->attributes['timezone'] ?? null,
            'currency' => $this->attributes['currency'] ?? null,
            'locale' => $this->attributes['locale'] ?? null,
            'tax_inclusive' => $this->attributes['tax_inclusive'] ?? null,
            'default_tax_id' => $this->attributes['default_tax_id'] ?? null,
            'logo_url' => $this->attributes['logo_url'] ?? null,
            'address' => $this->attributes['address'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $input  validated request fields
     * @return list<array{key: string, value: mixed, type: string, group: string, description: string}>
     */
    public static function upsertPayloads(array $input): array
    {
        $definitions = [
            'name' => ['store.name', 'string', 'Store display name'],
            'email' => ['store.email', 'string', 'Public store email'],
            'phone' => ['store.phone', 'string', 'Public store phone'],
            'timezone' => ['store.timezone', 'string', 'IANA timezone'],
            'currency' => ['store.currency', 'string', 'Default store currency'],
            'locale' => ['store.locale', 'string', 'Default locale'],
            'tax_inclusive' => ['store.tax_inclusive', 'boolean', 'Prices include tax'],
            'default_tax_id' => ['store.default_tax_id', 'integer', 'Default tax id'],
            'logo_url' => ['store.logo_url', 'string', 'Logo URL'],
            'address' => ['store.address', 'string', 'Store address'],
        ];

        $payloads = [];

        foreach ($definitions as $field => [$key, $type, $description]) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $payloads[] = [
                'key' => $key,
                'value' => $input[$field],
                'type' => $type,
                'group' => 'store',
                'description' => $description,
            ];
        }

        return $payloads;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
