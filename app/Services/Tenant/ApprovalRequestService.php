<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\ApprovalRequestStatus;
use App\Events\Tenant\Erp\ApprovalDecided;
use App\Models\Central\Tenant;
use App\Models\Tenant\ApprovalRequest;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\ReturnAuthorization;
use App\Models\Tenant\WarehouseTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Configurable approval requests against tenant documents.
 */
final class ApprovalRequestService
{
    /**
     * @return LengthAwarePaginator<int, ApprovalRequest>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(ApprovalRequest::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('approvable_type'),
                AllowedFilter::exact('approvable_id'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('status'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->with(['requester', 'decider', 'approvable'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     type: string,
     *     approvable_type: string,
     *     approvable_id: int,
     *     request_notes?: string|null
     * }  $data
     */
    public function create(array $data): ApprovalRequest
    {
        $type = $this->normalizeType($data['approvable_type']);
        $this->assertApprovable($type, $data['approvable_id']);

        $pendingExists = ApprovalRequest::query()
            ->where('approvable_type', $type)
            ->where('approvable_id', $data['approvable_id'])
            ->where('status', ApprovalRequestStatus::Pending)
            ->exists();

        if ($pendingExists) {
            throw ValidationException::withMessages([
                'approvable_id' => ['A pending approval already exists for this record.'],
            ]);
        }

        return ApprovalRequest::query()->create([
            'number' => 'APR-'.Str::upper(Str::random(10)),
            'type' => $data['type'],
            'approvable_type' => $type,
            'approvable_id' => $data['approvable_id'],
            'status' => ApprovalRequestStatus::Pending,
            'requested_by' => auth()->id(),
            'request_notes' => $data['request_notes'] ?? null,
        ])->load(['requester', 'approvable']);
    }

    public function find(ApprovalRequest $approvalRequest): ApprovalRequest
    {
        return $approvalRequest->loadMissing(['requester', 'decider', 'approvable']);
    }

    public function approve(ApprovalRequest $approvalRequest, ?string $decisionNotes = null): ApprovalRequest
    {
        return $this->decide($approvalRequest, ApprovalRequestStatus::Approved, $decisionNotes);
    }

    public function reject(ApprovalRequest $approvalRequest, ?string $decisionNotes = null): ApprovalRequest
    {
        return $this->decide($approvalRequest, ApprovalRequestStatus::Rejected, $decisionNotes);
    }

    public function cancel(ApprovalRequest $approvalRequest): ApprovalRequest
    {
        $this->assertPending($approvalRequest);

        $approvalRequest->update([
            'status' => ApprovalRequestStatus::Cancelled,
            'decided_at' => now(),
            'decided_by' => auth()->id(),
        ]);

        return $this->find($approvalRequest->refresh());
    }

    public function delete(ApprovalRequest $approvalRequest): void
    {
        $this->assertPending($approvalRequest);
        $approvalRequest->delete();
    }

    private function decide(
        ApprovalRequest $approvalRequest,
        ApprovalRequestStatus $status,
        ?string $decisionNotes,
    ): ApprovalRequest {
        $this->assertPending($approvalRequest);

        /** @var Tenant $tenant */
        $tenant = tenant();

        $approvalRequest->update([
            'status' => $status,
            'decision_notes' => $decisionNotes,
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);

        $approvalRequest = $this->find($approvalRequest->refresh());

        event(new ApprovalDecided($approvalRequest, (string) $tenant->getTenantKey()));

        return $approvalRequest;
    }

    private function assertPending(ApprovalRequest $approvalRequest): void
    {
        if ($approvalRequest->status !== ApprovalRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Approval request must be pending.'],
            ]);
        }
    }

    private function assertApprovable(string $type, int $id): void
    {
        if (! class_exists($type) || ! is_subclass_of($type, Model::class)) {
            throw ValidationException::withMessages([
                'approvable_type' => ['Unsupported approvable type.'],
            ]);
        }

        if (! $type::query()->whereKey($id)->exists()) {
            throw ValidationException::withMessages([
                'approvable_id' => ['The selected approvable is invalid.'],
            ]);
        }
    }

    private function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'purchase_order', 'purchaseorder', PurchaseOrder::class => PurchaseOrder::class,
            'warehouse_transfer', 'warehousetransfer', WarehouseTransfer::class => WarehouseTransfer::class,
            'return_authorization', 'returnauthorization', 'return', ReturnAuthorization::class => ReturnAuthorization::class,
            default => $type,
        };
    }
}
