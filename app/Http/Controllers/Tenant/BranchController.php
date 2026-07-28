<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Branch;
use App\Services\Tenant\BranchService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Branches')]
class BranchController extends Controller
{
    public function __construct(private BranchService $branches) {}

    /**
     * @operationId listBranches
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless(request()->user()?->can('warehouses.view') ?? false, 403);

        $perPage = max(1, min(100, (int) $request->integer('per_page', 15)));

        return ApiResponse::success(
            data: $this->branches->list($perPage)->items(),
            message: 'Branches retrieved successfully.',
        );
    }

    /**
     * @operationId createBranch
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless(request()->user()?->can('warehouses.create') ?? false, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:branches,code'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $branch = $this->branches->create($validated);

        return ApiResponse::success(
            data: $branch,
            message: 'Branch created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showBranch
     */
    #[PathParameter('branch', description: 'Branch ID.', type: 'integer', example: 1)]
    public function show(Branch $branch): JsonResponse
    {
        abort_unless(request()->user()?->can('warehouses.view') ?? false, 403);

        return ApiResponse::success(
            data: $this->branches->find($branch),
            message: 'Branch retrieved successfully.',
        );
    }

    /**
     * @operationId updateBranch
     */
    #[PathParameter('branch', description: 'Branch ID.', type: 'integer', example: 1)]
    public function update(Request $request, Branch $branch): JsonResponse
    {
        abort_unless(request()->user()?->can('warehouses.update') ?? false, 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('branches', 'code')->ignore($branch->id)],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::success(
            data: $this->branches->update($branch, $validated),
            message: 'Branch updated successfully.',
        );
    }

    /**
     * @operationId deleteBranch
     */
    #[PathParameter('branch', description: 'Branch ID.', type: 'integer', example: 1)]
    public function destroy(Branch $branch): JsonResponse
    {
        abort_unless(request()->user()?->can('warehouses.delete') ?? false, 403);
        $this->branches->delete($branch);

        return ApiResponse::success(message: 'Branch deleted successfully.');
    }
}
