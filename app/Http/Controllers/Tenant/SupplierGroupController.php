<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexSupplierGroupRequest;
use App\Http\Requests\Tenant\StoreSupplierGroupRequest;
use App\Http\Requests\Tenant\UpdateSupplierGroupRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\SupplierGroupResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\SupplierGroup;
use App\Services\Tenant\SupplierGroupService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Supplier Groups')]
class SupplierGroupController extends Controller
{
    public function __construct(private SupplierGroupService $groups) {}

    /**
     * @operationId listSupplierGroups
     */
    public function index(IndexSupplierGroupRequest $request): ResourceCollection
    {
        return SupplierGroupResource::collection($this->groups->list($request->perPage()))
            ->withMessage('Supplier groups retrieved successfully.');
    }

    /**
     * @operationId createSupplierGroup
     */
    #[DocsResponse(status: 201, description: 'Supplier group created.', type: 'array{success: true, message: string, data: SupplierGroupResource, meta: null, errors: null}')]
    public function store(StoreSupplierGroupRequest $request): JsonResponse
    {
        $group = $this->groups->create($request->groupData());

        return ApiResponse::success(
            data: (new SupplierGroupResource($group))->resolve(),
            message: 'Supplier group created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showSupplierGroup
     */
    #[PathParameter('supplier_group', description: 'Supplier group ID.', type: 'integer', example: 1)]
    public function show(SupplierGroup $supplierGroup): SupplierGroupResource
    {
        $this->authorize('view', $supplierGroup);

        return (new SupplierGroupResource($this->groups->find($supplierGroup)))
            ->withMessage('Supplier group retrieved successfully.');
    }

    /**
     * @operationId updateSupplierGroup
     */
    #[PathParameter('supplier_group', description: 'Supplier group ID.', type: 'integer', example: 1)]
    public function update(UpdateSupplierGroupRequest $request, SupplierGroup $supplierGroup): SupplierGroupResource
    {
        return (new SupplierGroupResource($this->groups->update($supplierGroup, $request->groupData())))
            ->withMessage('Supplier group updated successfully.');
    }

    /**
     * @operationId deleteSupplierGroup
     */
    #[PathParameter('supplier_group', description: 'Supplier group ID.', type: 'integer', example: 1)]
    public function destroy(SupplierGroup $supplierGroup): JsonResponse
    {
        $this->authorize('delete', $supplierGroup);
        $this->groups->delete($supplierGroup);

        return ApiResponse::success(message: 'Supplier group deleted successfully.');
    }
}
