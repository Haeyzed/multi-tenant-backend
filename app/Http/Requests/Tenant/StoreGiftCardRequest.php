<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\GiftCard;
use Illuminate\Foundation\Http\FormRequest;

class StoreGiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GiftCard::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'code' => ['nullable', 'string', 'max:64', 'unique:gift_cards,code'],
            'pin' => ['nullable', 'string', 'max:32'],
            'issued_to' => ['nullable', 'integer', 'exists:customers,id'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function giftCardData(): array
    {
        return $this->validated();
    }
}
