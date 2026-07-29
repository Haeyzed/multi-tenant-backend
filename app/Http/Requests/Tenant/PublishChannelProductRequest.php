<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Channel;
use Illuminate\Foundation\Http\FormRequest;

class PublishChannelProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Channel $channel */
        $channel = $this->route('channel');

        return $this->user()?->can('update', $channel) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ];
    }

    public function productId(): int
    {
        /** @var int $productId */
        $productId = $this->validated('product_id');

        return $productId;
    }
}
