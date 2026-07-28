<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexCustomerRequest;
use App\Http\Requests\Tenant\StoreCustomerRequest;
use App\Http\Requests\Tenant\UpdateCustomerRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Customer;
use App\Services\Tenant\CustomerService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Customers')]
class CustomerController extends Controller
{
    public function __construct(private CustomerService $customers) {}

    /**
     * @operationId listCustomers
     */
    public function index(IndexCustomerRequest $request): ResourceCollection
    {
        return CustomerResource::collection($this->customers->list($request->perPage()))
            ->withMessage('Customers retrieved successfully.');
    }

    /**
     * @operationId createCustomer
     */
    #[DocsResponse(status: 201, description: 'Customer created.', type: 'array{success: true, message: string, data: CustomerResource, meta: null, errors: null}')]
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create($request->customerData());

        return ApiResponse::success(
            data: (new CustomerResource($customer))->resolve(),
            message: 'Customer created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showCustomer
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return (new CustomerResource($this->customers->find($customer)))
            ->withMessage('Customer retrieved successfully.');
    }

    /**
     * @operationId updateCustomer
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        return (new CustomerResource($this->customers->update($customer, $request->customerData())))
            ->withMessage('Customer updated successfully.');
    }

    /**
     * @operationId deleteCustomer
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);
        $this->customers->delete($customer);

        return ApiResponse::success(message: 'Customer deleted successfully.');
    }
}
