<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexAttributeGroupRequest;
use App\Http\Requests\Tenant\StoreAttributeGroupRequest;
use App\Http\Requests\Tenant\UpdateAttributeGroupRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\AttributeGroupResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\AttributeGroup;
use App\Services\Tenant\AttributeService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Attribute Groups')]
class AttributeGroupController extends Controller
{
    public function __construct(private AttributeService $attributes) {}

    /**
     * @operationId listAttributeGroups
     */
    public function index(IndexAttributeGroupRequest $request): ResourceCollection
    {
        return AttributeGroupResource::collection($this->attributes->listGroups($request->perPage()))
            ->withMessage('Attribute groups retrieved successfully.');
    }

    /**
     * @operationId createAttributeGroup
     */
    #[DocsResponse(status: 201, description: 'Attribute group created.', type: 'array{success: true, message: string, data: AttributeGroupResource, meta: null, errors: null}')]
    public function store(StoreAttributeGroupRequest $request): JsonResponse
    {
        $group = $this->attributes->createGroup($request->groupData());

        return ApiResponse::success(
            data: (new AttributeGroupResource($group))->resolve(),
            message: 'Attribute group created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showAttributeGroup
     */
    #[PathParameter('attribute_group', description: 'Attribute group ID.', type: 'integer', example: 1)]
    public function show(AttributeGroup $attributeGroup): AttributeGroupResource
    {
        $this->authorize('view', $attributeGroup);

        return (new AttributeGroupResource($this->attributes->findGroup($attributeGroup)))
            ->withMessage('Attribute group retrieved successfully.');
    }

    /**
     * @operationId updateAttributeGroup
     */
    #[PathParameter('attribute_group', description: 'Attribute group ID.', type: 'integer', example: 1)]
    public function update(UpdateAttributeGroupRequest $request, AttributeGroup $attributeGroup): AttributeGroupResource
    {
        return (new AttributeGroupResource($this->attributes->updateGroup($attributeGroup, $request->groupData())))
            ->withMessage('Attribute group updated successfully.');
    }

    /**
     * @operationId deleteAttributeGroup
     */
    #[PathParameter('attribute_group', description: 'Attribute group ID.', type: 'integer', example: 1)]
    public function destroy(AttributeGroup $attributeGroup): JsonResponse
    {
        $this->authorize('delete', $attributeGroup);
        $this->attributes->deleteGroup($attributeGroup);

        return ApiResponse::success(message: 'Attribute group deleted successfully.');
    }
}
