<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexCollectionRequest;
use App\Http\Requests\Tenant\StoreCollectionRequest;
use App\Http\Requests\Tenant\SyncCollectionProductsRequest;
use App\Http\Requests\Tenant\UpdateCollectionRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\CollectionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Collection;
use App\Services\Tenant\CollectionService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Collections')]
class CollectionController extends Controller
{
    public function __construct(private CollectionService $collections) {}

    /**
     * @operationId listCollections
     */
    public function index(IndexCollectionRequest $request): ResourceCollection
    {
        return CollectionResource::collection($this->collections->list($request->perPage()))
            ->withMessage('Collections retrieved successfully.');
    }

    /**
     * @operationId createCollection
     */
    #[DocsResponse(status: 201, description: 'Collection created.', type: 'array{success: true, message: string, data: CollectionResource, meta: null, errors: null}')]
    public function store(StoreCollectionRequest $request): JsonResponse
    {
        $collection = $this->collections->create($request->collectionData());

        return ApiResponse::success(
            data: (new CollectionResource($collection))->resolve(),
            message: 'Collection created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showCollection
     */
    #[PathParameter('collection', description: 'Collection ID.', type: 'integer', example: 1)]
    public function show(Collection $collection): CollectionResource
    {
        $this->authorize('view', $collection);

        return (new CollectionResource($this->collections->find($collection)))
            ->withMessage('Collection retrieved successfully.');
    }

    /**
     * @operationId updateCollection
     */
    #[PathParameter('collection', description: 'Collection ID.', type: 'integer', example: 1)]
    public function update(UpdateCollectionRequest $request, Collection $collection): CollectionResource
    {
        return (new CollectionResource($this->collections->update($collection, $request->collectionData())))
            ->withMessage('Collection updated successfully.');
    }

    /**
     * @operationId deleteCollection
     */
    #[PathParameter('collection', description: 'Collection ID.', type: 'integer', example: 1)]
    public function destroy(Collection $collection): JsonResponse
    {
        $this->authorize('delete', $collection);
        $this->collections->delete($collection);

        return ApiResponse::success(message: 'Collection deleted successfully.');
    }

    /**
     * @operationId syncCollectionRules
     */
    #[PathParameter('collection', description: 'Collection ID.', type: 'integer', example: 1)]
    public function syncRules(Collection $collection): CollectionResource
    {
        $this->authorize('update', $collection);

        return (new CollectionResource($this->collections->syncSmartRules($collection)))
            ->withMessage('Collection rules synced successfully.');
    }

    /**
     * @operationId syncCollectionProducts
     */
    #[PathParameter('collection', description: 'Collection ID.', type: 'integer', example: 1)]
    public function syncProducts(SyncCollectionProductsRequest $request, Collection $collection): CollectionResource
    {
        return (new CollectionResource($this->collections->syncProducts($collection, $request->productIds())))
            ->withMessage('Collection products synced successfully.');
    }
}
