<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexAttributeValueRequest;
use App\Http\Requests\Tenant\StoreAttributeValueRequest;
use App\Http\Requests\Tenant\UpdateAttributeValueRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\AttributeValueResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Attribute;
use App\Models\Tenant\AttributeValue;
use App\Services\Tenant\AttributeService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Attribute Values')]
class AttributeValueController extends Controller
{
    public function __construct(private AttributeService $attributes) {}

    /**
     * @operationId listAttributeValues
     */
    #[PathParameter('attribute', description: 'Attribute ID.', type: 'integer', example: 1)]
    public function index(IndexAttributeValueRequest $request, Attribute $attribute): ResourceCollection
    {
        return AttributeValueResource::collection($this->attributes->listValues($attribute, $request->perPage()))
            ->withMessage('Attribute values retrieved successfully.');
    }

    /**
     * @operationId createAttributeValue
     */
    #[PathParameter('attribute', description: 'Attribute ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Attribute value created.', type: 'array{success: true, message: string, data: AttributeValueResource, meta: null, errors: null}')]
    public function store(StoreAttributeValueRequest $request, Attribute $attribute): JsonResponse
    {
        $value = $this->attributes->createValue($attribute, $request->valueData());

        return ApiResponse::success(
            data: (new AttributeValueResource($value))->resolve(),
            message: 'Attribute value created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showAttributeValue
     */
    #[PathParameter('attribute', description: 'Attribute ID.', type: 'integer', example: 1)]
    #[PathParameter('value', description: 'Attribute value ID.', type: 'integer', example: 1)]
    public function show(Attribute $attribute, AttributeValue $value): AttributeValueResource
    {
        abort_unless($value->attribute_id === $attribute->id, 404);
        $this->authorize('view', $attribute);

        return (new AttributeValueResource($this->attributes->findValue($value)))
            ->withMessage('Attribute value retrieved successfully.');
    }

    /**
     * @operationId updateAttributeValue
     */
    #[PathParameter('attribute', description: 'Attribute ID.', type: 'integer', example: 1)]
    #[PathParameter('value', description: 'Attribute value ID.', type: 'integer', example: 1)]
    public function update(UpdateAttributeValueRequest $request, Attribute $attribute, AttributeValue $value): AttributeValueResource
    {
        abort_unless($value->attribute_id === $attribute->id, 404);

        return (new AttributeValueResource($this->attributes->updateValue($value, $request->valueData())))
            ->withMessage('Attribute value updated successfully.');
    }

    /**
     * @operationId deleteAttributeValue
     */
    #[PathParameter('attribute', description: 'Attribute ID.', type: 'integer', example: 1)]
    #[PathParameter('value', description: 'Attribute value ID.', type: 'integer', example: 1)]
    public function destroy(Attribute $attribute, AttributeValue $value): JsonResponse
    {
        abort_unless($value->attribute_id === $attribute->id, 404);
        $this->authorize('update', $attribute);
        $this->attributes->deleteValue($value);

        return ApiResponse::success(message: 'Attribute value deleted successfully.');
    }
}
