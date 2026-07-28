<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCustomerNoteRequest;
use App\Http\Requests\Tenant\UpdateCustomerNoteRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\CustomerNoteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerNote;
use App\Services\Tenant\CustomerCrmService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Customer Notes')]
class CustomerNoteController extends Controller
{
    public function __construct(private CustomerCrmService $crm) {}

    /**
     * @operationId listCustomerNotes
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    public function index(Request $request, Customer $customer): ResourceCollection
    {
        $this->authorize('view', $customer);

        return CustomerNoteResource::collection(
            $this->crm->listNotes($customer, (int) $request->integer('per_page', 15))
        )->withMessage('Customer notes retrieved successfully.');
    }

    /**
     * @operationId createCustomerNote
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Note created.', type: 'array{success: true, message: string, data: CustomerNoteResource, meta: null, errors: null}')]
    public function store(StoreCustomerNoteRequest $request, Customer $customer): JsonResponse
    {
        $note = $this->crm->createNote($customer, $request->noteData());

        return ApiResponse::success(
            data: (new CustomerNoteResource($note))->resolve(),
            message: 'Customer note created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId updateCustomerNote
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[PathParameter('note', description: 'Note ID.', type: 'integer', example: 1)]
    public function update(
        UpdateCustomerNoteRequest $request,
        Customer $customer,
        CustomerNote $note,
    ): CustomerNoteResource {
        abort_unless($note->customer_id === $customer->id, 404);

        return (new CustomerNoteResource($this->crm->updateNote($customer, $note, $request->noteData())))
            ->withMessage('Customer note updated successfully.');
    }

    /**
     * @operationId deleteCustomerNote
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[PathParameter('note', description: 'Note ID.', type: 'integer', example: 1)]
    public function destroy(Customer $customer, CustomerNote $note): JsonResponse
    {
        abort_unless($note->customer_id === $customer->id, 404);
        $this->authorize('update', $customer);
        $this->crm->deleteNote($customer, $note);

        return ApiResponse::success(message: 'Customer note deleted successfully.');
    }
}
