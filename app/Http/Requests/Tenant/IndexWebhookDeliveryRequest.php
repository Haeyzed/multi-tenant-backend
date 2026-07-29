<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\WebhookEndpoint;
use Illuminate\Foundation\Http\FormRequest;

class IndexWebhookDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WebhookEndpoint $webhookEndpoint */
        $webhookEndpoint = $this->route('webhook_endpoint');

        return $this->user()?->can('view', $webhookEndpoint) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.status' => ['sometimes', 'string'],
            'filter.event' => ['sometimes', 'string'],
            'sort' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', 15);
    }
}
