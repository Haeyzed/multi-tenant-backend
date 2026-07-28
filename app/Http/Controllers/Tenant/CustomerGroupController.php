<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexCustomerGroupRequest;
use App\Http\Requests\Tenant\StoreCustomerGroupRequest;
use App\Http\Requests\Tenant\UpdateCustomerGroupRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\CustomerGroupResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\CustomerGroup;
use App\Services\Tenant\CustomerGroupService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Customer Groups')]
class CustomerGroupController extends Controller
{
    public function __construct(private CustomerGroupService $groups) {}

    /**
     * @operationId listCustomerGroups
     */
    public function index(IndexCustomerGroupRequest $request): ResourceCollection
    {
        return CustomerGroupResource::collection($this->groups->list($request->perPage()))
            ->withMessage('Customer groups retrieved successfully.');
    }

    /**
     * @operationId createCustomerGroup
     */
    #[DocsResponse(status: 201, description: 'Customer group created.', type: 'array{success: true, message: string, data: CustomerGroupResource, meta: null, errors: null}')]
    public function store(StoreCustomerGroupRequest $request): JsonResponse
    {
        $group = $this->groups->create($request->groupData());

        return ApiResponse::success(
            data: (new CustomerGroupResource($group))->resolve(),
            message: 'Customer group created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showCustomerGroup
     */
    #[PathParameter('customer_group', description: 'Customer group ID.', type: 'integer', example: 1)]
    public function show(CustomerGroup $customerGroup): CustomerGroupResource
    {
        $this->authorize('view', $customerGroup);

        return (new CustomerGroupResource($this->groups->find($customerGroup)))
            ->withMessage('Customer group retrieved successfully.');
    }

    /**
     * @operationId updateCustomerGroup
     */
    #[PathParameter('customer_group', description: 'Customer group ID.', type: 'integer', example: 1)]
    public function update(UpdateCustomerGroupRequest $request, CustomerGroup $customerGroup): CustomerGroupResource
    {
        return (new CustomerGroupResource($this->groups->update($customerGroup, $request->groupData())))
            ->withMessage('Customer group updated successfully.');
    }

    /**
     * @operationId deleteCustomerGroup
     */
    #[PathParameter('customer_group', description: 'Customer group ID.', type: 'integer', example: 1)]
    public function destroy(CustomerGroup $customerGroup): JsonResponse
    {
        $this->authorize('delete', $customerGroup);
        $this->groups->delete($customerGroup);

        return ApiResponse::success(message: 'Customer group deleted successfully.');
    }
}
