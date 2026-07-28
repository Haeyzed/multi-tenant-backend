<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Tenant\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ReportRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\ReportService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

#[Group('Reports')]
class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    /**
     * @operationId salesSummaryReport
     */
    public function salesSummary(ReportRequest $request): JsonResponse
    {
        abort_unless(request()->user()?->can(Permission::ReportsView->value) ?? false, 403);

        return ApiResponse::success(
            data: $this->reports->salesSummary(
                $this->date($request->input('from')),
                $this->date($request->input('to')),
            ),
            message: 'Sales summary retrieved successfully.',
        );
    }

    /**
     * @operationId topProductsReport
     */
    public function topProducts(ReportRequest $request): JsonResponse
    {
        abort_unless(request()->user()?->can(Permission::ReportsView->value) ?? false, 403);

        return ApiResponse::success(
            data: $this->reports->topProducts(
                $this->date($request->input('from')),
                $this->date($request->input('to')),
                (int) $request->integer('limit', 10),
            ),
            message: 'Top products retrieved successfully.',
        );
    }

    /**
     * @operationId lowStockReport
     */
    public function lowStock(ReportRequest $request): JsonResponse
    {
        abort_unless(request()->user()?->can(Permission::ReportsView->value) ?? false, 403);

        return ApiResponse::success(
            data: $this->reports->lowStock((int) $request->integer('threshold', 5)),
            message: 'Low stock report retrieved successfully.',
        );
    }

    private function date(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value);
    }
}
