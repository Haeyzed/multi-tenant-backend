<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\ChangeSubscriptionPlanRequest;
use App\Http\Requests\Central\StoreSubscriptionRequest;
use App\Http\Resources\Central\EntitlementsResource;
use App\Http\Resources\Central\SubscriptionHistoryResource;
use App\Http\Resources\Central\SubscriptionResource;
use App\Http\Resources\ResourceCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Central\EntitlementService;
use App\Services\Central\SubscriptionHistoryService;
use App\Services\Central\SubscriptionService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Subscriptions')]
class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private EntitlementService $entitlements,
        private SubscriptionHistoryService $history,
    ) {}

    /**
     * @operationId showTenantSubscription
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid')]
    public function show(Tenant $tenant): SubscriptionResource|JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $subscription = $this->entitlements->currentSubscription($tenant);

        if ($subscription === null) {
            return ApiResponse::success(
                data: null,
                message: 'Tenant has no active subscription.',
            );
        }

        return (new SubscriptionResource($subscription))
            ->withMessage('Subscription retrieved successfully.');
    }

    /**
     * @operationId showTenantEntitlements
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid')]
    public function entitlements(Tenant $tenant): EntitlementsResource
    {
        $this->authorize('viewAny', Subscription::class);

        return (new EntitlementsResource($this->entitlements->forTenant($tenant)))
            ->withMessage('Entitlements retrieved successfully.');
    }

    /**
     * @operationId subscribeTenant
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid')]
    #[DocsResponse(status: 201, description: 'Subscribed.', type: 'array{success: true, message: string, data: SubscriptionResource, meta: null, errors: null}')]
    public function store(StoreSubscriptionRequest $request, Tenant $tenant): JsonResponse
    {
        $subscription = $this->subscriptions->subscribe($tenant, $request->subscriptionData());

        return ApiResponse::success(
            data: (new SubscriptionResource($subscription))->resolve(),
            message: 'Tenant subscribed successfully.',
            status: 201,
        );
    }

    /**
     * @operationId cancelTenantSubscription
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid')]
    public function cancel(Request $request, Tenant $tenant): SubscriptionResource
    {
        $subscription = $this->requireCurrentSubscription($tenant);
        $this->authorize('update', $subscription);

        $atPeriodEnd = $request->boolean('at_period_end', true);

        return (new SubscriptionResource($this->subscriptions->cancel($subscription, $atPeriodEnd)))
            ->withMessage('Subscription cancelled successfully.');
    }

    /**
     * @operationId resumeTenantSubscription
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid')]
    public function resume(Tenant $tenant): SubscriptionResource
    {
        /** @var Subscription $subscription */
        $subscription = $tenant->subscriptions()->latest('id')->firstOrFail();
        $this->authorize('update', $subscription);

        return (new SubscriptionResource($this->subscriptions->resume($subscription)))
            ->withMessage('Subscription resumed successfully.');
    }

    /**
     * @operationId changeTenantSubscriptionPlan
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid')]
    public function changePlan(ChangeSubscriptionPlanRequest $request, Tenant $tenant): SubscriptionResource
    {
        $subscription = $this->requireCurrentSubscription($tenant);

        return (new SubscriptionResource(
            $this->subscriptions->changePlan($subscription, $request->planChangeData())
        ))->withMessage('Subscription plan changed successfully.');
    }

    /**
     * @operationId listTenantSubscriptionHistory
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid')]
    public function history(Request $request, Tenant $tenant): ResourceCollection
    {
        $this->authorize('viewAny', Subscription::class);

        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));

        return SubscriptionHistoryResource::collection(
            $this->history->listForTenant((string) $tenant->getTenantKey(), $perPage)
        )->withMessage('Subscription history retrieved successfully.');
    }

    /**
     * @operationId suspendTenantSubscription
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid')]
    public function suspend(Tenant $tenant): SubscriptionResource
    {
        $subscription = $this->requireCurrentSubscription($tenant);
        $this->authorize('update', $subscription);

        return (new SubscriptionResource($this->subscriptions->suspend($subscription, 'admin')))
            ->withMessage('Subscription suspended successfully.');
    }

    private function requireCurrentSubscription(Tenant $tenant): Subscription
    {
        $subscription = $this->entitlements->currentSubscription($tenant);

        abort_if($subscription === null, 404, 'Tenant has no active subscription.');

        return $subscription;
    }
}
