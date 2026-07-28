<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\WarehouseTransfer;
use Illuminate\Foundation\Http\FormRequest;

class DispatchWarehouseTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WarehouseTransfer $transfer */
        $transfer = $this->route('warehouse_transfer');

        return $this->user()?->can('dispatch', $transfer) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'dispatch_notes' => ['nullable', 'string'],
        ];
    }
}
