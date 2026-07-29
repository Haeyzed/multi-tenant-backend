<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexBillOfMaterialRequest;
use App\Http\Requests\Tenant\StoreBillOfMaterialRequest;
use App\Http\Requests\Tenant\UpdateBillOfMaterialRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\BillOfMaterialResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\BillOfMaterial;
use App\Services\Tenant\BillOfMaterialService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Bill of Materials')]
class BillOfMaterialController extends Controller
{
    public function __construct(private BillOfMaterialService $billOfMaterials) {}

    /**
     * @operationId listBillOfMaterials
     */
    public function index(IndexBillOfMaterialRequest $request): ResourceCollection
    {
        return BillOfMaterialResource::collection($this->billOfMaterials->list($request->perPage()))
            ->withMessage('Bills of materials retrieved successfully.');
    }

    /**
     * @operationId createBillOfMaterial
     */
    #[DocsResponse(status: 201, description: 'Bill of materials created.', type: 'array{success: true, message: string, data: BillOfMaterialResource, meta: null, errors: null}')]
    public function store(StoreBillOfMaterialRequest $request): JsonResponse
    {
        $bom = $this->billOfMaterials->create($request->billOfMaterialData());

        return ApiResponse::success(
            data: (new BillOfMaterialResource($bom))->resolve(),
            message: 'Bill of materials created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showBillOfMaterial
     */
    #[PathParameter('bill_of_material', description: 'Bill of materials ID.', type: 'integer', example: 1)]
    public function show(BillOfMaterial $billOfMaterial): BillOfMaterialResource
    {
        $this->authorize('view', $billOfMaterial);

        return (new BillOfMaterialResource($this->billOfMaterials->find($billOfMaterial)))
            ->withMessage('Bill of materials retrieved successfully.');
    }

    /**
     * @operationId updateBillOfMaterial
     */
    #[PathParameter('bill_of_material', description: 'Bill of materials ID.', type: 'integer', example: 1)]
    public function update(UpdateBillOfMaterialRequest $request, BillOfMaterial $billOfMaterial): BillOfMaterialResource
    {
        return (new BillOfMaterialResource($this->billOfMaterials->update($billOfMaterial, $request->billOfMaterialData())))
            ->withMessage('Bill of materials updated successfully.');
    }

    /**
     * @operationId deleteBillOfMaterial
     */
    #[PathParameter('bill_of_material', description: 'Bill of materials ID.', type: 'integer', example: 1)]
    public function destroy(BillOfMaterial $billOfMaterial): JsonResponse
    {
        $this->authorize('delete', $billOfMaterial);
        $this->billOfMaterials->delete($billOfMaterial);

        return ApiResponse::success(message: 'Bill of materials deleted successfully.');
    }
}
