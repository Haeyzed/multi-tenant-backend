<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Order;
use Illuminate\Foundation\Http\FormRequest;

class MarkOrderBackorderedRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()?->can('update', $order) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
