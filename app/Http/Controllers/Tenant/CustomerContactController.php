<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCustomerContactRequest;
use App\Http\Requests\Tenant\UpdateCustomerContactRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\CustomerContactResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerContact;
use App\Services\Tenant\CustomerCrmService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Customer Contacts')]
class CustomerContactController extends Controller
{
    public function __construct(private CustomerCrmService $crm) {}

    /**
     * @operationId listCustomerContacts
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    public function index(Request $request, Customer $customer): ResourceCollection
    {
        $this->authorize('view', $customer);

        return CustomerContactResource::collection(
            $this->crm->listContacts($customer, (int) $request->integer('per_page', 15))
        )->withMessage('Customer contacts retrieved successfully.');
    }

    /**
     * @operationId createCustomerContact
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Contact created.', type: 'array{success: true, message: string, data: CustomerContactResource, meta: null, errors: null}')]
    public function store(StoreCustomerContactRequest $request, Customer $customer): JsonResponse
    {
        $contact = $this->crm->createContact($customer, $request->contactData());

        return ApiResponse::success(
            data: (new CustomerContactResource($contact))->resolve(),
            message: 'Customer contact created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showCustomerContact
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[PathParameter('contact', description: 'Contact ID.', type: 'integer', example: 1)]
    public function show(Customer $customer, CustomerContact $contact): CustomerContactResource
    {
        abort_unless($contact->customer_id === $customer->id, 404);
        $this->authorize('view', $customer);

        return (new CustomerContactResource($contact))
            ->withMessage('Customer contact retrieved successfully.');
    }

    /**
     * @operationId updateCustomerContact
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[PathParameter('contact', description: 'Contact ID.', type: 'integer', example: 1)]
    public function update(
        UpdateCustomerContactRequest $request,
        Customer $customer,
        CustomerContact $contact,
    ): CustomerContactResource {
        abort_unless($contact->customer_id === $customer->id, 404);

        return (new CustomerContactResource($this->crm->updateContact($customer, $contact, $request->contactData())))
            ->withMessage('Customer contact updated successfully.');
    }

    /**
     * @operationId deleteCustomerContact
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[PathParameter('contact', description: 'Contact ID.', type: 'integer', example: 1)]
    public function destroy(Customer $customer, CustomerContact $contact): JsonResponse
    {
        abort_unless($contact->customer_id === $customer->id, 404);
        $this->authorize('update', $customer);
        $this->crm->deleteContact($customer, $contact);

        return ApiResponse::success(message: 'Customer contact deleted successfully.');
    }
}
