<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockLedgerEntry;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\StockLedgerService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Inventory')]
class InventoryController extends Controller
{
    public function __construct(private StockLedgerService $ledger) {}

    /**
     * @operationId inventoryLevels
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    public function levels(Warehouse $warehouse, Product $product): JsonResponse
    {
        abort_unless(request()->user()?->can('warehouses.view') ?? false, 403);

        return ApiResponse::success(
            data: array_merge([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'sku' => $product->sku,
            ], $this->ledger->levels($warehouse, $product)),
            message: 'Inventory levels retrieved successfully.',
        );
    }

    /**
     * @operationId listStockLedger
     */
    public function ledger(Request $request): JsonResponse
    {
        abort_unless(request()->user()?->can('warehouses.view') ?? false, 403);

        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));

        $entries = QueryBuilder::for(StockLedgerEntry::class)
            ->with(['warehouse', 'product'])
            ->allowedFilters(
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('reason'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-id')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(
            data: $entries->items(),
            message: 'Stock ledger retrieved successfully.',
            meta: [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        );
    }
}
