<?php

declare(strict_types=1);

namespace App\Jobs\Tenant;

use App\Models\Tenant\DataJob;
use App\Services\Tenant\DataJobService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $dataJobId) {}

    public function handle(DataJobService $dataJobs): void
    {
        /** @var DataJob|null $job */
        $job = DataJob::query()->find($this->dataJobId);

        if ($job === null) {
            return;
        }

        $dataJobs->process($job);
    }
}
