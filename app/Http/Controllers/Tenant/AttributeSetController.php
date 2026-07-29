<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\AttributeSetResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\AttributeSet;
use App\Services\Tenant\AttributeSetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttributeSetController extends Controller
{
    public function __construct(private AttributeSetService $attributeSets) {}

    /** @operationId listAttributeSets */
    public function index(Request $request): ResourceCollection
    {
        $this->authorize('viewAny', AttributeSet::class);

        return AttributeSetResource::collection($this->attributeSets->list((int) $request->integer('per_page', 15)));
    }

    /** @operationId createAttributeSet */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AttributeSet::class);
        $attributeSet = $this->attributeSets->create($request->validate(['name' => ['required', 'string', 'max:255'], 'code' => ['sometimes', 'string', 'max:255', 'unique:attribute_sets,code'], 'product_family_id' => ['nullable', 'integer', 'exists:product_families,id'], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']]));

        return ApiResponse::success(data: (new AttributeSetResource($attributeSet))->resolve(), message: 'Attribute set created successfully.', status: 201);
    }

    /** @operationId showAttributeSet */
    public function show(AttributeSet $attributeSet): AttributeSetResource
    {
        $this->authorize('view', $attributeSet);

        return new AttributeSetResource($this->attributeSets->find($attributeSet));
    }

    /** @operationId updateAttributeSet */
    public function update(Request $request, AttributeSet $attributeSet): AttributeSetResource
    {
        $this->authorize('update', $attributeSet);

        return new AttributeSetResource($this->attributeSets->update($attributeSet, $request->validate(['name' => ['sometimes', 'string', 'max:255'], 'code' => ['sometimes', 'string', 'max:255', 'unique:attribute_sets,code,'.$attributeSet->id], 'product_family_id' => ['nullable', 'integer', 'exists:product_families,id'], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']])));
    }

    /** @operationId syncAttributeSetAttributes */
    public function syncAttributes(Request $request, AttributeSet $attributeSet): AttributeSetResource
    {
        $this->authorize('update', $attributeSet);

        return new AttributeSetResource($this->attributeSets->syncAttributes($attributeSet, $request->validate(['attributes' => ['required', 'array'], 'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'], 'attributes.*.position' => ['sometimes', 'integer', 'min:0'], 'attributes.*.is_required' => ['sometimes', 'boolean']])['attributes']));
    }

    /** @operationId deleteAttributeSet */
    public function destroy(AttributeSet $attributeSet): JsonResponse
    {
        $this->authorize('delete', $attributeSet);
        $this->attributeSets->delete($attributeSet);

        return ApiResponse::success(message: 'Attribute set deleted successfully.');
    }
}
