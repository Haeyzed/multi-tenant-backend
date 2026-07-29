<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property-read Media $resource
 *
 * @mixin Media
 */
#[SchemaName('UploadedProductMedia')]
class UploadedProductMediaResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection' => $this->collection_name,
            'file_name' => $this->file_name,
            'url' => $this->getUrl(),
            'mime_type' => $this->mime_type,
            'size' => $this->size,
        ];
    }
}
