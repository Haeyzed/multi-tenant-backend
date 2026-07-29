<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreSupplierContactRequest;
use App\Http\Requests\Tenant\UpdateSupplierContactRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\SupplierContactResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierContact;
use App\Services\Tenant\SupplierCrmService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Supplier Contacts')]
class SupplierContactController extends Controller
{
    public function __construct(private SupplierCrmService $crm) {}

    /**
     * @operationId listSupplierContacts
     */
    #[PathParameter('supplier', description: 'Supplier ID.', type: 'integer', example: 1)]
    public function index(Request $request, Supplier $supplier): ResourceCollection
    {
        $this->authorize('view', $supplier);

        return SupplierContactResource::collection(
            $this->crm->listContacts($supplier, (int) $request->integer('per_page', 15))
        )->withMessage('Supplier contacts retrieved successfully.');
    }

    /**
     * @operationId createSupplierContact
     */
    #[PathParameter('supplier', description: 'Supplier ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Contact created.', type: 'array{success: true, message: string, data: SupplierContactResource, meta: null, errors: null}')]
    public function store(StoreSupplierContactRequest $request, Supplier $supplier): JsonResponse
    {
        $contact = $this->crm->createContact($supplier, $request->validated());

        return ApiResponse::success(
            data: (new SupplierContactResource($contact))->resolve(),
            message: 'Supplier contact created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showSupplierContact
     */
    #[PathParameter('supplier', description: 'Supplier ID.', type: 'integer', example: 1)]
    #[PathParameter('contact', description: 'Contact ID.', type: 'integer', example: 1)]
    public function show(Supplier $supplier, SupplierContact $contact): SupplierContactResource
    {
        abort_unless($contact->supplier_id === $supplier->id, 404);
        $this->authorize('view', $supplier);

        return (new SupplierContactResource($contact))
            ->withMessage('Supplier contact retrieved successfully.');
    }

    /**
     * @operationId updateSupplierContact
     */
    #[PathParameter('supplier', description: 'Supplier ID.', type: 'integer', example: 1)]
    #[PathParameter('contact', description: 'Contact ID.', type: 'integer', example: 1)]
    public function update(
        UpdateSupplierContactRequest $request,
        Supplier $supplier,
        SupplierContact $contact,
    ): SupplierContactResource {
        abort_unless($contact->supplier_id === $supplier->id, 404);

        return (new SupplierContactResource($this->crm->updateContact($supplier, $contact, $request->validated())))
            ->withMessage('Supplier contact updated successfully.');
    }

    /**
     * @operationId deleteSupplierContact
     */
    #[PathParameter('supplier', description: 'Supplier ID.', type: 'integer', example: 1)]
    #[PathParameter('contact', description: 'Contact ID.', type: 'integer', example: 1)]
    public function destroy(Supplier $supplier, SupplierContact $contact): JsonResponse
    {
        abort_unless($contact->supplier_id === $supplier->id, 404);
        $this->authorize('update', $supplier);
        $this->crm->deleteContact($supplier, $contact);

        return ApiResponse::success(message: 'Supplier contact deleted successfully.');
    }
}
