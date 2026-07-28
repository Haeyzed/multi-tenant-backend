<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Tenant\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCustomerTagRequest;
use App\Http\Requests\Tenant\SyncCustomerTagsRequest;
use App\Http\Requests\Tenant\UpdateCustomerTagRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\CustomerResource;
use App\Http\Resources\Tenant\CustomerTagResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerTag;
use App\Services\Tenant\CustomerCrmService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Customer Tags')]
class CustomerTagController extends Controller
{
    public function __construct(private CustomerCrmService $crm) {}

    /**
     * @operationId listCustomerTags
     */
    public function index(Request $request): ResourceCollection
    {
        abort_unless(request()->user()?->can(Permission::CustomersView->value) ?? false, 403);

        return CustomerTagResource::collection(
            $this->crm->listTags((int) $request->integer('per_page', 15))
        )->withMessage('Customer tags retrieved successfully.');
    }

    /**
     * @operationId createCustomerTag
     */
    #[DocsResponse(status: 201, description: 'Tag created.', type: 'array{success: true, message: string, data: CustomerTagResource, meta: null, errors: null}')]
    public function store(StoreCustomerTagRequest $request): JsonResponse
    {
        $tag = $this->crm->createTag($request->tagData());

        return ApiResponse::success(
            data: (new CustomerTagResource($tag))->resolve(),
            message: 'Customer tag created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId updateCustomerTag
     */
    #[PathParameter('customer_tag', description: 'Customer tag ID.', type: 'integer', example: 1)]
    public function update(UpdateCustomerTagRequest $request, CustomerTag $customerTag): CustomerTagResource
    {
        return (new CustomerTagResource($this->crm->updateTag($customerTag, $request->tagData())))
            ->withMessage('Customer tag updated successfully.');
    }

    /**
     * @operationId deleteCustomerTag
     */
    #[PathParameter('customer_tag', description: 'Customer tag ID.', type: 'integer', example: 1)]
    public function destroy(CustomerTag $customerTag): JsonResponse
    {
        abort_unless(request()->user()?->can(Permission::CustomersDelete->value) ?? false, 403);
        $this->crm->deleteTag($customerTag);

        return ApiResponse::success(message: 'Customer tag deleted successfully.');
    }

    /**
     * @operationId syncCustomerTags
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    public function sync(SyncCustomerTagsRequest $request, Customer $customer): CustomerResource
    {
        return (new CustomerResource($this->crm->syncTags($customer, $request->tagIds())))
            ->withMessage('Customer tags synced successfully.');
    }
}
