<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\WebhookEndpoint;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WebhookEndpoint $webhookEndpoint */
        $webhookEndpoint = $this->route('webhook_endpoint');

        return $this->user()?->can('update', $webhookEndpoint) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'string', 'url', 'max:2048'],
            'secret' => ['nullable', 'string', 'max:255'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name?: string, url?: string, secret?: string|null, events?: list<string>, is_active?: bool}
     */
    public function webhookEndpointData(): array
    {
        return $this->validated();
    }
}
