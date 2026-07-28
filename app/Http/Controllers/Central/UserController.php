<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\IndexCentralUserRequest;
use App\Http\Requests\Central\StoreCentralUserRequest;
use App\Http\Requests\Central\UpdateCentralUserRequest;
use App\Http\Resources\Central\UserResource;
use App\Http\Resources\ResourceCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Central\User;
use App\Services\Central\UserService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Central Users')]
class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    /**
     * @operationId listCentralUsers
     */
    public function index(IndexCentralUserRequest $request): ResourceCollection
    {
        return UserResource::collection($this->users->list($request->perPage()))
            ->withMessage('Central users retrieved successfully.');
    }

    /**
     * @operationId createCentralUser
     */
    #[DocsResponse(status: 201, description: 'Central user created.', type: 'array{success: true, message: string, data: UserResource, meta: null, errors: null}')]
    public function store(StoreCentralUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->userData());

        return ApiResponse::success(
            data: (new UserResource($user))->resolve(),
            message: 'Central user created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showCentralUser
     */
    #[PathParameter('user', description: 'Central user ID.', type: 'integer', example: 1)]
    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return (new UserResource($this->users->find($user)))
            ->withMessage('Central user retrieved successfully.');
    }

    /**
     * @operationId updateCentralUser
     */
    #[PathParameter('user', description: 'Central user ID.', type: 'integer', example: 1)]
    public function update(UpdateCentralUserRequest $request, User $user): UserResource
    {
        return (new UserResource($this->users->update($user, $request->userData())))
            ->withMessage('Central user updated successfully.');
    }

    /**
     * @operationId deleteCentralUser
     */
    #[PathParameter('user', description: 'Central user ID.', type: 'integer', example: 1)]
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);
        /** @var User $actor */
        $actor = request()->user();
        $this->users->delete($user, $actor);

        return ApiResponse::success(message: 'Central user deleted successfully.');
    }
}
