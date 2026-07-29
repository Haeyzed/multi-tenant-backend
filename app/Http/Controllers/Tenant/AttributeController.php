<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexAttributeRequest;
use App\Http\Requests\Tenant\StoreAttributeRequest;
use App\Http\Requests\Tenant\UpdateAttributeRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\AttributeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Attribute;
use App\Services\Tenant\AttributeService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Attributes')]
class AttributeController extends Controller
{
    public function __construct(private AttributeService $attributes) {}

    /**
     * @operationId listAttributes
     */
    public function index(IndexAttributeRequest $request): ResourceCollection
    {
        return AttributeResource::collection($this->attributes->listAttributes($request->perPage()))
            ->withMessage('Attributes retrieved successfully.');
    }

    /**
     * @operationId createAttribute
     */
    #[DocsResponse(status: 201, description: 'Attribute created.', type: 'array{success: true, message: string, data: AttributeResource, meta: null, errors: null}')]
    public function store(StoreAttributeRequest $request): JsonResponse
    {
        $attribute = $this->attributes->createAttribute($request->attributeData());

        return ApiResponse::success(
            data: (new AttributeResource($attribute))->resolve(),
            message: 'Attribute created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showAttribute
     */
    #[PathParameter('attribute', description: 'Attribute ID.', type: 'integer', example: 1)]
    public function show(Attribute $attribute): AttributeResource
    {
        $this->authorize('view', $attribute);

        return (new AttributeResource($this->attributes->findAttribute($attribute)))
            ->withMessage('Attribute retrieved successfully.');
    }

    /**
     * @operationId updateAttribute
     */
    #[PathParameter('attribute', description: 'Attribute ID.', type: 'integer', example: 1)]
    public function update(UpdateAttributeRequest $request, Attribute $attribute): AttributeResource
    {
        return (new AttributeResource($this->attributes->updateAttribute($attribute, $request->attributeData())))
            ->withMessage('Attribute updated successfully.');
    }

    /**
     * @operationId deleteAttribute
     */
    #[PathParameter('attribute', description: 'Attribute ID.', type: 'integer', example: 1)]
    public function destroy(Attribute $attribute): JsonResponse
    {
        $this->authorize('delete', $attribute);
        $this->attributes->deleteAttribute($attribute);

        return ApiResponse::success(message: 'Attribute deleted successfully.');
    }
}
