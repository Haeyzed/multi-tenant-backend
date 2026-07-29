<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ClosePosSessionRequest;
use App\Http\Requests\Tenant\IndexPosSessionRequest;
use App\Http\Requests\Tenant\OpenPosSessionRequest;
use App\Http\Requests\Tenant\PosSaleRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\OrderResource;
use App\Http\Resources\Tenant\PosSessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\PosSession;
use App\Services\Tenant\PosSessionService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('POS Sessions')]
class PosSessionController extends Controller
{
    public function __construct(private PosSessionService $sessions) {}

    /**
     * @operationId listPosSessions
     */
    public function index(IndexPosSessionRequest $request): ResourceCollection
    {
        return PosSessionResource::collection($this->sessions->list($request->perPage()))
            ->withMessage('POS sessions retrieved successfully.');
    }

    /**
     * @operationId openPosSession
     */
    #[DocsResponse(status: 201, description: 'POS session opened.', type: 'array{success: true, message: string, data: PosSessionResource, meta: null, errors: null}')]
    public function store(OpenPosSessionRequest $request): JsonResponse
    {
        $session = $this->sessions->open($request->sessionData());

        return ApiResponse::success(
            data: (new PosSessionResource($session))->resolve(),
            message: 'POS session opened successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showPosSession
     */
    #[PathParameter('pos_session', description: 'POS session ID.', type: 'integer', example: 1)]
    public function show(PosSession $posSession): PosSessionResource
    {
        $this->authorize('view', $posSession);

        return (new PosSessionResource($this->sessions->find($posSession)))
            ->withMessage('POS session retrieved successfully.');
    }

    /**
     * @operationId deletePosSession
     */
    #[PathParameter('pos_session', description: 'POS session ID.', type: 'integer', example: 1)]
    public function destroy(PosSession $posSession): JsonResponse
    {
        $this->authorize('delete', $posSession);
        $posSession->delete();

        return ApiResponse::success(message: 'POS session deleted successfully.');
    }

    /**
     * @operationId closePosSession
     */
    #[PathParameter('pos_session', description: 'POS session ID.', type: 'integer', example: 1)]
    public function close(ClosePosSessionRequest $request, PosSession $posSession): PosSessionResource
    {
        return (new PosSessionResource($this->sessions->close($posSession, $request->closeData())))
            ->withMessage('POS session closed successfully.');
    }

    /**
     * @operationId createPosSale
     */
    #[PathParameter('pos_session', description: 'POS session ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'POS sale created.', type: 'array{success: true, message: string, data: OrderResource, meta: null, errors: null}')]
    public function sale(PosSaleRequest $request, PosSession $posSession): JsonResponse
    {
        $order = $this->sessions->sale($posSession, $request->saleData());

        return ApiResponse::success(
            data: (new OrderResource($order))->resolve(),
            message: 'POS sale created successfully.',
            status: 201,
        );
    }
}
