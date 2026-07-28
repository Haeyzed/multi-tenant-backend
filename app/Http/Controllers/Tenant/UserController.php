<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexUserRequest;
use App\Http\Requests\Tenant\StoreUserRequest;
use App\Http\Requests\Tenant\UpdateUserRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\User;
use App\Services\Tenant\UserService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

/**
 * Tenant user management endpoints.
 *
 * CRUD operations for users scoped to the current tenant database.
 */
#[Group('Users')]
class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    /**
     * List tenant users.
     *
     * Returns a paginated collection of users in the current tenant. Supports Spatie
     * Query Builder filters, sorts, and includes via query parameters.
     *
     * @operationId listUsers
     */
    public function index(IndexUserRequest $request): ResourceCollection
    {
        $users = $this->users->list($request->perPage());

        return UserResource::collection($users)
            ->withMessage('Users retrieved successfully.');
    }

    /**
     * Create a tenant user.
     *
     * Registers a new user in the current tenant database.
     *
     * @operationId createUser
     */
    #[DocsResponse(
        status: 201,
        description: 'User created successfully.',
        type: 'array{success: true, message: string, data: UserResource, meta: null, errors: null}',
    )]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->userData());

        return ApiResponse::success(
            data: (new UserResource($user))->resolve(),
            message: 'User created successfully.',
            status: 201,
        );
    }

    /**
     * Show a tenant user.
     *
     * Returns a single user from the current tenant, including assigned roles.
     *
     * @operationId showUser
     *
     * @param  User  $user  The user identified by their numeric primary key.
     */
    #[PathParameter('user', description: 'User ID.', type: 'integer', example: 1)]
    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return (new UserResource($this->users->find($user)))
            ->withMessage('User retrieved successfully.');
    }

    /**
     * Update a tenant user.
     *
     * Updates profile fields and optionally the assigned role for a tenant user.
     *
     * @operationId updateUser
     *
     * @param  User  $user  The user identified by their numeric primary key.
     */
    #[PathParameter('user', description: 'User ID.', type: 'integer', example: 1)]
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user = $this->users->update($user, $request->userData());

        return (new UserResource($user))
            ->withMessage('User updated successfully.');
    }

    /**
     * Delete a tenant user.
     *
     * Permanently removes a user from the current tenant. Users cannot delete their own account.
     *
     * @operationId deleteUser
     *
     * @param  User  $user  The user identified by their numeric primary key.
     *
     * @response array{
     *     success: true,
     *     message: string,
     *     data: null,
     *     meta: null,
     *     errors: null
     * }
     */
    #[PathParameter('user', description: 'User ID.', type: 'integer', example: 1)]
    public function destroy(User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = request()->user();

        $this->authorize('delete', $user);

        $this->users->delete($user, $actor);

        return ApiResponse::success(message: 'User deleted successfully.');
    }
}
