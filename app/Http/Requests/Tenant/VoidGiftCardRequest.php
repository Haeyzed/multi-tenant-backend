<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\GiftCard;
use Illuminate\Foundation\Http\FormRequest;

class VoidGiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GiftCard $giftCard */
        $giftCard = $this->route('gift_card');

        return $this->user()?->can('update', $giftCard) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
