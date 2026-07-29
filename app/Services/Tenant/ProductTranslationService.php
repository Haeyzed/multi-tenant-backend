<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductTranslation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductTranslationService
{
    /**
     * @return Collection<int, ProductTranslation>
     */
    public function list(Product $product): Collection
    {
        return $product->translations()->orderBy('locale')->get();
    }

    /**
     * @param  array{
     *     name: string,
     *     slug?: string|null,
     *     description?: string|null,
     *     meta_title?: string|null,
     *     meta_description?: string|null
     * }  $data
     */
    public function upsert(Product $product, string $locale, array $data): ProductTranslation
    {
        $locale = Str::lower($locale);

        if ($locale === '') {
            throw ValidationException::withMessages([
                'locale' => ['The locale is required.'],
            ]);
        }

        /** @var ProductTranslation $translation */
        $translation = ProductTranslation::query()->firstOrNew([
            'product_id' => $product->id,
            'locale' => $locale,
        ]);

        $translation->fill([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ])->save();

        return $translation->refresh();
    }

    public function delete(Product $product, string $locale): void
    {
        ProductTranslation::query()
            ->where('product_id', $product->id)
            ->where('locale', Str::lower($locale))
            ->delete();
    }
}
