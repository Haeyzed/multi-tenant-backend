<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\DataJobResource as DataJobResourceEnum;
use App\Enums\Tenant\DataJobType;
use App\Models\Tenant\DataJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDataJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DataJob::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DataJobType::class)],
            'resource' => ['required', Rule::enum(DataJobResourceEnum::class)],
            'options' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array{type: string, resource: string, options?: array<string, mixed>|null}
     */
    public function dataJobData(): array
    {
        return $this->validated();
    }
}
