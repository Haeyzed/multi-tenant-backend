<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexCreditNoteRequest;
use App\Http\Requests\Tenant\StoreCreditNoteRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\CreditNoteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\CreditNote;
use App\Services\Tenant\CreditNoteService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Credit Notes')]
class CreditNoteController extends Controller
{
    public function __construct(private CreditNoteService $creditNotes) {}

    /**
     * @operationId listCreditNotes
     */
    public function index(IndexCreditNoteRequest $request): ResourceCollection
    {
        return CreditNoteResource::collection($this->creditNotes->list($request->perPage()))
            ->withMessage('Credit notes retrieved successfully.');
    }

    /**
     * @operationId createCreditNote
     */
    #[DocsResponse(status: 201, description: 'Credit note created.', type: 'array{success: true, message: string, data: CreditNoteResource, meta: null, errors: null}')]
    public function store(StoreCreditNoteRequest $request): JsonResponse
    {
        $creditNote = $this->creditNotes->create($request->creditNoteData());

        return ApiResponse::success(
            data: (new CreditNoteResource($creditNote))->resolve(),
            message: 'Credit note created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showCreditNote
     */
    #[PathParameter('credit_note', description: 'Credit note ID.', type: 'integer', example: 1)]
    public function show(CreditNote $creditNote): CreditNoteResource
    {
        $this->authorize('view', $creditNote);

        return (new CreditNoteResource($this->creditNotes->find($creditNote)))
            ->withMessage('Credit note retrieved successfully.');
    }

    /**
     * @operationId deleteCreditNote
     */
    #[PathParameter('credit_note', description: 'Credit note ID.', type: 'integer', example: 1)]
    public function destroy(CreditNote $creditNote): JsonResponse
    {
        $this->authorize('delete', $creditNote);
        $this->creditNotes->delete($creditNote);

        return ApiResponse::success(message: 'Credit note deleted successfully.');
    }

    /**
     * @operationId issueCreditNote
     */
    #[PathParameter('credit_note', description: 'Credit note ID.', type: 'integer', example: 1)]
    public function issue(CreditNote $creditNote): CreditNoteResource
    {
        $this->authorize('issue', $creditNote);

        return (new CreditNoteResource($this->creditNotes->issue($creditNote)))
            ->withMessage('Credit note issued successfully.');
    }

    /**
     * @operationId voidCreditNote
     */
    #[PathParameter('credit_note', description: 'Credit note ID.', type: 'integer', example: 1)]
    public function void(CreditNote $creditNote): CreditNoteResource
    {
        $this->authorize('void', $creditNote);

        return (new CreditNoteResource($this->creditNotes->void($creditNote)))
            ->withMessage('Credit note voided successfully.');
    }
}
