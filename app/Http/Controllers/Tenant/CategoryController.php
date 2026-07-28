<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexCategoryRequest;
use App\Http\Requests\Tenant\StoreCategoryRequest;
use App\Http\Requests\Tenant\UpdateCategoryRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\CategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Category;
use App\Services\Tenant\CategoryService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Categories')]
class CategoryController extends Controller
{
    public function __construct(private CategoryService $categories) {}

    /**
     * @operationId listCategories
     */
    public function index(IndexCategoryRequest $request): ResourceCollection
    {
        return CategoryResource::collection($this->categories->list($request->perPage()))
            ->withMessage('Categories retrieved successfully.');
    }

    /**
     * @operationId createCategory
     */
    #[DocsResponse(status: 201, description: 'Category created.', type: 'array{success: true, message: string, data: CategoryResource, meta: null, errors: null}')]
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create($request->categoryData());

        return ApiResponse::success(
            data: (new CategoryResource($category))->resolve(),
            message: 'Category created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showCategory
     */
    #[PathParameter('category', description: 'Category ID.', type: 'integer', example: 1)]
    public function show(Category $category): CategoryResource
    {
        $this->authorize('view', $category);

        return (new CategoryResource($this->categories->find($category)))
            ->withMessage('Category retrieved successfully.');
    }

    /**
     * @operationId updateCategory
     */
    #[PathParameter('category', description: 'Category ID.', type: 'integer', example: 1)]
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        return (new CategoryResource($this->categories->update($category, $request->categoryData())))
            ->withMessage('Category updated successfully.');
    }

    /**
     * @operationId deleteCategory
     */
    #[PathParameter('category', description: 'Category ID.', type: 'integer', example: 1)]
    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);
        $this->categories->delete($category);

        return ApiResponse::success(message: 'Category deleted successfully.');
    }
}
