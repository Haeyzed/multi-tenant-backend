<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductMedia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant product media management.
 */
final class ProductMediaService
{
    /**
     * @return LengthAwarePaginator<int, ProductMedia>
     */
    public function list(Product $product, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(ProductMedia::class)
            ->where('product_id', $product->id)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_primary'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('type'),
                AllowedSort::field('position'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('position')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{type: string, url: string, alt_text?: string|null, position?: int, is_primary?: bool}  $data
     */
    public function create(Product $product, array $data): ProductMedia
    {
        if ($data['is_primary'] ?? false) {
            ProductMedia::query()
                ->where('product_id', $product->id)
                ->update(['is_primary' => false]);
        }

        return ProductMedia::query()->create([
            'product_id' => $product->id,
            'type' => $data['type'],
            'url' => $data['url'],
            'alt_text' => $data['alt_text'] ?? null,
            'position' => $data['position'] ?? 0,
            'is_primary' => $data['is_primary'] ?? false,
        ]);
    }

    public function find(ProductMedia $media): ProductMedia
    {
        return $media;
    }

    /**
     * @param  array{type?: string, url?: string, alt_text?: string|null, position?: int, is_primary?: bool}  $data
     */
    public function update(ProductMedia $media, array $data): ProductMedia
    {
        if ($data['is_primary'] ?? false) {
            ProductMedia::query()
                ->where('product_id', $media->product_id)
                ->where('id', '!=', $media->id)
                ->update(['is_primary' => false]);
        }

        $media->fill($data)->save();

        return $media->refresh();
    }

    public function delete(ProductMedia $media): void
    {
        $media->delete();
    }

    public function upload(Product $product, UploadedFile $file, string $collection = 'gallery'): Media
    {
        return $product->addMedia($file)->toMediaCollection($collection);
    }
}
