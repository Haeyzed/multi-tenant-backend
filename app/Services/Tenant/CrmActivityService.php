<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\CrmActivityType;
use App\Models\Tenant\CrmActivity;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Lead;
use App\Models\Tenant\Opportunity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * CRM activities against leads, opportunities, or customers.
 */
final class CrmActivityService
{
    /**
     * @return LengthAwarePaginator<int, CrmActivity>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(CrmActivity::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('subjectable_type'),
                AllowedFilter::exact('subjectable_id'),
                AllowedFilter::exact('user_id'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('due_at'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->with(['user', 'subjectable'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     type?: string,
     *     subject?: string|null,
     *     body?: string|null,
     *     subjectable_type: string,
     *     subjectable_id: int,
     *     due_at?: string|null,
     *     completed_at?: string|null
     * }  $data
     */
    public function create(array $data): CrmActivity
    {
        $this->assertSubjectable($data['subjectable_type'], $data['subjectable_id']);

        return CrmActivity::query()->create([
            'type' => $data['type'] ?? CrmActivityType::Note->value,
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'] ?? null,
            'subjectable_type' => $this->normalizeType($data['subjectable_type']),
            'subjectable_id' => $data['subjectable_id'],
            'user_id' => auth()->id(),
            'due_at' => $data['due_at'] ?? null,
            'completed_at' => $data['completed_at'] ?? null,
        ])->load(['user', 'subjectable']);
    }

    public function find(CrmActivity $activity): CrmActivity
    {
        return $activity->loadMissing(['user', 'subjectable']);
    }

    /**
     * @param  array{
     *     type?: string,
     *     subject?: string|null,
     *     body?: string|null,
     *     due_at?: string|null,
     *     completed_at?: string|null
     * }  $data
     */
    public function update(CrmActivity $activity, array $data): CrmActivity
    {
        $activity->fill($data)->save();

        return $this->find($activity->refresh());
    }

    public function delete(CrmActivity $activity): void
    {
        $activity->delete();
    }

    public function complete(CrmActivity $activity): CrmActivity
    {
        $activity->update(['completed_at' => now()]);

        return $this->find($activity->refresh());
    }

    private function assertSubjectable(string $type, int $id): void
    {
        $modelClass = $this->normalizeType($type);

        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw ValidationException::withMessages([
                'subjectable_type' => ['Unsupported subjectable type.'],
            ]);
        }

        if (! $modelClass::query()->whereKey($id)->exists()) {
            throw ValidationException::withMessages([
                'subjectable_id' => ['The selected subjectable is invalid.'],
            ]);
        }
    }

    private function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'lead', Lead::class => Lead::class,
            'opportunity', Opportunity::class => Opportunity::class,
            'customer', Customer::class => Customer::class,
            default => $type,
        };
    }
}
