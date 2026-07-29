<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\GiftCard;
use Illuminate\Foundation\Http\FormRequest;

class CheckGiftCardBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', GiftCard::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
        ];
    }
}
