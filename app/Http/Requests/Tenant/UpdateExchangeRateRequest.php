<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ExchangeRate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ExchangeRate $exchangeRate */
        $exchangeRate = $this->route('exchange_rate');

        return $this->user()?->can('update', $exchangeRate) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'currency_from' => ['sometimes', 'string', 'size:3'],
            'currency_to' => ['sometimes', 'string', 'size:3'],
            'rate' => ['sometimes', 'numeric', 'gt:0'],
            'effective_at' => ['sometimes', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeRateData(): array
    {
        return $this->validated();
    }
}
