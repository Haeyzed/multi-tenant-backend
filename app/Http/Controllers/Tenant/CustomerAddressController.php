<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCustomerAddressRequest;
use App\Http\Requests\Tenant\UpdateCustomerAddressRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\CustomerAddressResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Services\Tenant\CustomerCrmService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Customer Addresses')]
class CustomerAddressController extends Controller
{
    public function __construct(private CustomerCrmService $crm) {}

    /**
     * @operationId listCustomerAddresses
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    public function index(Request $request, Customer $customer): ResourceCollection
    {
        $this->authorize('view', $customer);

        return CustomerAddressResource::collection(
            $this->crm->listAddresses($customer, (int) $request->integer('per_page', 15))
        )->withMessage('Customer addresses retrieved successfully.');
    }

    /**
     * @operationId createCustomerAddress
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Address created.', type: 'array{success: true, message: string, data: CustomerAddressResource, meta: null, errors: null}')]
    public function store(StoreCustomerAddressRequest $request, Customer $customer): JsonResponse
    {
        $address = $this->crm->createAddress($customer, $request->addressData());

        return ApiResponse::success(
            data: (new CustomerAddressResource($address))->resolve(),
            message: 'Customer address created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showCustomerAddress
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[PathParameter('address', description: 'Address ID.', type: 'integer', example: 1)]
    public function show(Customer $customer, CustomerAddress $address): CustomerAddressResource
    {
        abort_unless($address->customer_id === $customer->id, 404);
        $this->authorize('view', $customer);

        return (new CustomerAddressResource($address))
            ->withMessage('Customer address retrieved successfully.');
    }

    /**
     * @operationId updateCustomerAddress
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[PathParameter('address', description: 'Address ID.', type: 'integer', example: 1)]
    public function update(
        UpdateCustomerAddressRequest $request,
        Customer $customer,
        CustomerAddress $address,
    ): CustomerAddressResource {
        abort_unless($address->customer_id === $customer->id, 404);

        return (new CustomerAddressResource($this->crm->updateAddress($customer, $address, $request->addressData())))
            ->withMessage('Customer address updated successfully.');
    }

    /**
     * @operationId deleteCustomerAddress
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[PathParameter('address', description: 'Address ID.', type: 'integer', example: 1)]
    public function destroy(Customer $customer, CustomerAddress $address): JsonResponse
    {
        abort_unless($address->customer_id === $customer->id, 404);
        $this->authorize('update', $customer);
        $this->crm->deleteAddress($customer, $address);

        return ApiResponse::success(message: 'Customer address deleted successfully.');
    }
}
