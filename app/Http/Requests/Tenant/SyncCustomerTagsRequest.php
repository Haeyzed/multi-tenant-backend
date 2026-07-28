<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Customer;
use Illuminate\Foundation\Http\FormRequest;

class SyncCustomerTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return $this->user()?->can('update', $customer) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['integer', 'exists:customer_tags,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function tagIds(): array
    {
        /** @var list<int> $ids */
        $ids = $this->validated('tag_ids');

        return $ids;
    }
}
