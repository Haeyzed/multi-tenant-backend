<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Tenant\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CancelTenantSubscriptionRequest;
use App\Http\Requests\Tenant\ChangeTenantSubscriptionPlanRequest;
use App\Http\Requests\Tenant\IndexTenantBillingInvoiceRequest;
use App\Http\Requests\Tenant\StoreTenantSubscriptionRequest;
use App\Http\Resources\Central\EntitlementsResource;
use App\Http\Resources\Central\InvoiceResource;
use App\Http\Resources\Central\PlanResource;
use App\Http\Resources\Central\SubscriptionResource;
use App\Http\Resources\ResourceCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Central\Invoice;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Central\EntitlementService;
use App\Services\Central\InvoiceService;
use App\Services\Central\PlanService;
use App\Services\Central\SubscriptionService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant self-serve billing: catalog, subscription lifecycle, and invoices.
 */
#[Group('Billing')]
class BillingController extends Controller
{
    public function __construct(
        private EntitlementService $entitlements,
        private SubscriptionService $subscriptions,
        private PlanService $plans,
        private InvoiceService $invoices,
    ) {}

    /**
     * @operationId tenantEntitlements
     */
    public function entitlements(): EntitlementsResource
    {
        abort_unless($this->userCan(Permission::BillingView), 403);

        /** @var Tenant $tenant */
        $tenant = tenant();

        return (new EntitlementsResource($this->entitlements->forTenant($tenant)))
            ->withMessage('Entitlements retrieved successfully.');
    }

    /**
     * @operationId listTenantBillingPlans
     */
    public function plans(Request $request): ResourceCollection
    {
        abort_unless($this->userCan(Permission::BillingView), 403);

        $perPage = min(100, max(1, (int) $request->integer('per_page', 50)));

        return PlanResource::collection($this->plans->listActive($perPage))
            ->withMessage('Billing plans retrieved successfully.');
    }

    /**
     * @operationId tenantSubscription
     */
    public function subscription(): SubscriptionResource|JsonResponse
    {
        abort_unless($this->userCan(Permission::BillingView), 403);

        /** @var Tenant $tenant */
        $tenant = tenant();

        $subscription = $this->entitlements->currentSubscription($tenant);

        if ($subscription === null) {
            return ApiResponse::success(
                data: null,
                message: 'No active subscription.',
            );
        }

        return (new SubscriptionResource($subscription))
            ->withMessage('Subscription retrieved successfully.');
    }

    /**
     * @operationId tenantSubscribe
     */
    #[DocsResponse(status: 201, description: 'Subscribed.', type: 'array{success: true, message: string, data: SubscriptionResource, meta: null, errors: null}')]
    public function subscribe(StoreTenantSubscriptionRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        $subscription = $this->subscriptions->subscribe($tenant, $request->subscriptionData());

        return ApiResponse::success(
            data: (new SubscriptionResource($subscription))->resolve(),
            message: 'Subscribed successfully.',
            status: 201,
        );
    }

    /**
     * @operationId tenantCancelSubscription
     */
    public function cancel(CancelTenantSubscriptionRequest $request): SubscriptionResource
    {
        $subscription = $this->requireCurrentSubscription();

        return (new SubscriptionResource(
            $this->subscriptions->cancel($subscription, $request->atPeriodEnd())
        ))->withMessage('Subscription cancelled successfully.');
    }

    /**
     * @operationId tenantResumeSubscription
     */
    public function resume(): SubscriptionResource
    {
        abort_unless($this->userCan(Permission::BillingManage), 403);

        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var Subscription $subscription */
        $subscription = $tenant->subscriptions()->latest('id')->firstOrFail();

        return (new SubscriptionResource($this->subscriptions->resume($subscription)))
            ->withMessage('Subscription resumed successfully.');
    }

    /**
     * @operationId tenantChangeSubscriptionPlan
     */
    public function changePlan(ChangeTenantSubscriptionPlanRequest $request): SubscriptionResource
    {
        $subscription = $this->requireCurrentSubscription();

        return (new SubscriptionResource(
            $this->subscriptions->changePlan($subscription, $request->planChangeData())
        ))->withMessage('Subscription plan changed successfully.');
    }

    /**
     * @operationId listTenantBillingInvoices
     */
    public function invoices(IndexTenantBillingInvoiceRequest $request): ResourceCollection
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        return InvoiceResource::collection(
            $this->invoices->listForTenant($tenant, $request->perPage())
        )->withMessage('Invoices retrieved successfully.');
    }

    /**
     * @operationId showTenantBillingInvoice
     */
    #[PathParameter('invoice', description: 'Invoice ID.', type: 'integer', example: 1)]
    public function showInvoice(Invoice $invoice): InvoiceResource
    {
        abort_unless($this->userCan(Permission::BillingView), 403);

        /** @var Tenant $tenant */
        $tenant = tenant();

        return (new InvoiceResource($this->invoices->findForTenant($tenant, $invoice)))
            ->withMessage('Invoice retrieved successfully.');
    }

    private function requireCurrentSubscription(): Subscription
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $subscription = $this->entitlements->currentSubscription($tenant);

        abort_if($subscription === null, 404, 'No active subscription.');

        return $subscription;
    }

    private function userCan(Permission $permission): bool
    {
        return request()->user()?->can($permission->value) ?? false;
    }
}
