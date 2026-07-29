<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\DataJobResource;
use App\Enums\Tenant\DataJobStatus;
use App\Enums\Tenant\DataJobType;
use App\Jobs\Tenant\ProcessDataJob;
use App\Models\Tenant\Customer;
use App\Models\Tenant\DataJob;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Import/export data job orchestration.
 */
final class DataJobService
{
    /**
     * @return LengthAwarePaginator<int, DataJob>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(DataJob::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('resource'),
                AllowedFilter::exact('status'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('status'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{type: string, resource: string, options?: array<string, mixed>|null}  $data
     */
    public function create(array $data): DataJob
    {
        $job = DataJob::query()->create([
            'number' => 'JOB-'.Str::upper(Str::random(10)),
            'type' => $data['type'],
            'resource' => $data['resource'],
            'status' => DataJobStatus::Pending,
            'options' => $data['options'] ?? null,
            'created_by' => auth()->id(),
        ]);

        ProcessDataJob::dispatch($job->id);

        return $job->refresh();
    }

    public function find(DataJob $dataJob): DataJob
    {
        return $dataJob->loadMissing('creator');
    }

    public function cancel(DataJob $dataJob): DataJob
    {
        if (! in_array($dataJob->status, [DataJobStatus::Pending, DataJobStatus::Processing], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only pending or processing jobs can be cancelled.'],
            ]);
        }

        $dataJob->update([
            'status' => DataJobStatus::Cancelled,
            'finished_at' => now(),
        ]);

        return $this->find($dataJob->refresh());
    }

    public function delete(DataJob $dataJob): void
    {
        if (in_array($dataJob->status, [DataJobStatus::Pending, DataJobStatus::Processing], true)) {
            throw ValidationException::withMessages([
                'status' => ['Cancel the job before deleting it.'],
            ]);
        }

        $dataJob->delete();
    }

    public function process(DataJob $dataJob): DataJob
    {
        if ($dataJob->status === DataJobStatus::Cancelled) {
            return $dataJob;
        }

        $dataJob->update([
            'status' => DataJobStatus::Processing,
            'started_at' => now(),
            'error_message' => null,
        ]);

        try {
            $count = match ($dataJob->resource) {
                DataJobResource::Products => Product::query()->count(),
                DataJobResource::Customers => Customer::query()->count(),
                DataJobResource::Orders => Order::query()->count(),
            };

            $dataJob->update([
                'status' => DataJobStatus::Completed,
                'finished_at' => now(),
                'result' => [
                    'type' => $dataJob->type->value,
                    'resource' => $dataJob->resource->value,
                    'records' => $count,
                    'message' => $dataJob->type === DataJobType::Export
                        ? "Exported {$count} {$dataJob->resource->value} records."
                        : "Import staged for {$count} existing {$dataJob->resource->value} records.",
                ],
            ]);
        } catch (\Throwable $e) {
            $dataJob->update([
                'status' => DataJobStatus::Failed,
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $this->find($dataJob->refresh());
    }
}
