<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ProductMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadProductMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductMedia::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'max:10240',
            ],
            'collection' => ['sometimes', 'string', Rule::in(['gallery', 'documents'])],
        ];
    }

    public function collectionName(): string
    {
        /** @var string|null $collection */
        $collection = $this->validated('collection');

        return $collection ?? 'gallery';
    }
}
