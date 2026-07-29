<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Tenant\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexNotificationRequest;
use App\Http\Responses\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;

#[Group('Notifications')]
class NotificationController extends Controller
{
    /**
     * @operationId listNotifications
     */
    public function index(IndexNotificationRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $query = $user->notifications()->latest();

        if ($request->unreadOnly()) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate($request->perPage());

        return ApiResponse::success(
            data: $notifications->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ])->all(),
            message: 'Notifications retrieved successfully.',
            meta: [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        );
    }

    /**
     * @operationId markNotificationAsRead
     */
    #[PathParameter('notification', description: 'Notification UUID.', type: 'string')]
    public function markAsRead(string $notification): JsonResponse
    {
        abort_unless(request()->user()?->can(Permission::NotificationsUpdate->value) ?? false, 403);

        $user = request()->user();
        abort_unless($user !== null, 403);

        $record = $user->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return ApiResponse::success(message: 'Notification marked as read.');
    }

    /**
     * @operationId markAllNotificationsAsRead
     */
    public function markAllAsRead(): JsonResponse
    {
        abort_unless(request()->user()?->can(Permission::NotificationsUpdate->value) ?? false, 403);

        $user = request()->user();
        abort_unless($user !== null, 403);

        $user->unreadNotifications->markAsRead();

        return ApiResponse::success(message: 'All notifications marked as read.');
    }
}
