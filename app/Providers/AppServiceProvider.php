<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Tenant\InventoryValuationStrategy;
use App\Contracts\Tenant\PushSender;
use App\Contracts\Tenant\ShippingLabelProvider;
use App\Contracts\Tenant\SmsSender;
use App\Enums\Billing\FeatureFlagKey;
use App\Events\Tenant\Erp\ApprovalDecided;
use App\Events\Tenant\Erp\GiftCardRedeemed;
use App\Events\Tenant\Erp\OrderConfirmed;
use App\Events\Tenant\Erp\PaymentRecorded;
use App\Events\Tenant\Erp\PurchaseRequestApproved;
use App\Events\Tenant\Erp\RfqQuoteAccepted;
use App\Events\Tenant\Erp\RfqSent;
use App\Events\Tenant\Erp\StockCountPosted;
use App\Events\Tenant\Erp\SupplierInvoiceIssued;
use App\Events\Tenant\Erp\SupplierPaymentRecorded;
use App\Events\Tenant\Erp\WorkOrderCompleted;
use App\Listeners\DispatchTenantWebhooks;
use App\Listeners\Tenant\NotifyOnErpEvent;
use App\Models\Central\Coupon;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\User as CentralUser;
use App\Models\Tenant\User as TenantUser;
use App\Policies\Central\CouponPolicy;
use App\Policies\Central\PlanPolicy;
use App\Policies\Central\SubscriptionPolicy;
use App\Services\Central\FeatureFlagService;
use App\Services\Central\TenantApiQuotaService;
use App\Services\Tenant\FifoCostService;
use App\Services\Tenant\LifoCostService;
use App\Services\Tenant\Notifications\FcmPushSender;
use App\Services\Tenant\Notifications\LogPushSender;
use App\Services\Tenant\Notifications\LogSmsSender;
use App\Services\Tenant\Notifications\NullPushSender;
use App\Services\Tenant\Notifications\TwilioSmsSender;
use App\Services\Tenant\Shipping\EasyPostShippingLabelProvider;
use App\Services\Tenant\Shipping\ManualShippingLabelProvider;
use App\Services\Tenant\Shipping\NullShippingLabelProvider;
use App\Services\Tenant\WeightedAverageCostService;
use Dedoc\Scramble\Scramble;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Scramble::ignoreDefaultRoutes();

        $this->app->bind(ShippingLabelProvider::class, function ($app): ShippingLabelProvider {
            return match ((string) config('services.shipping_label.driver', 'manual')) {
                'easypost' => $app->make(EasyPostShippingLabelProvider::class),
                'null' => $app->make(NullShippingLabelProvider::class),
                default => $app->make(ManualShippingLabelProvider::class),
            };
        });

        $this->app->bind(SmsSender::class, function ($app): SmsSender {
            return match ((string) config('services.sms.driver', 'log')) {
                'twilio' => $app->make(TwilioSmsSender::class),
                default => $app->make(LogSmsSender::class),
            };
        });

        $this->app->bind(PushSender::class, function ($app): PushSender {
            return match ((string) config('services.push.driver', 'null')) {
                'fcm' => $app->make(FcmPushSender::class),
                'log' => $app->make(LogPushSender::class),
                default => $app->make(NullPushSender::class),
            };
        });

        $this->app->bind(InventoryValuationStrategy::class, function ($app): InventoryValuationStrategy {
            $flags = $app->make(FeatureFlagService::class);

            if ($flags->enabled(FeatureFlagKey::ErpInventoryLifo, false)) {
                return $app->make(LifoCostService::class);
            }

            if ($flags->enabled(FeatureFlagKey::ErpInventoryFifo, false)) {
                return $app->make(FifoCostService::class);
            }

            return $app->make(WeightedAverageCostService::class);
        });
    }

    public function boot(): void
    {
        $this->configurePasswordDefaults();
        $this->configureRateLimiting();
        $this->configureEmailVerificationUrls();
        $this->configureApiDocumentation();
        $this->configurePolicies();
        $this->configureWebhookListeners();
        $this->configureErpNotificationListeners();
    }

    private function configureErpNotificationListeners(): void
    {
        $listener = NotifyOnErpEvent::class;

        Event::listen(PurchaseRequestApproved::class, [$listener, 'handlePurchaseRequestApproved']);
        Event::listen(PaymentRecorded::class, [$listener, 'handlePaymentRecorded']);
        Event::listen(StockCountPosted::class, [$listener, 'handleStockCountPosted']);
        Event::listen(SupplierPaymentRecorded::class, [$listener, 'handleSupplierPaymentRecorded']);
        Event::listen(SupplierInvoiceIssued::class, [$listener, 'handleSupplierInvoiceIssued']);
        Event::listen(RfqSent::class, [$listener, 'handleRfqSent']);
        Event::listen(RfqQuoteAccepted::class, [$listener, 'handleRfqQuoteAccepted']);
        Event::listen(GiftCardRedeemed::class, [$listener, 'handleGiftCardRedeemed']);
    }

    private function configureWebhookListeners(): void
    {
        Event::listen(OrderConfirmed::class, [DispatchTenantWebhooks::class, 'handleOrderConfirmed']);
        Event::listen(WorkOrderCompleted::class, [DispatchTenantWebhooks::class, 'handleWorkOrderCompleted']);
        Event::listen(ApprovalDecided::class, [DispatchTenantWebhooks::class, 'handleApprovalDecided']);
    }

    private function configurePolicies(): void
    {
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Coupon::class, CouponPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
    }

    private function configurePasswordDefaults(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(8);

            return app()->isProduction()
                ? $rule->mixedCase()->numbers()->symbols()->uncompromised()
                : $rule;
        });
    }

    private function configureEmailVerificationUrls(): void
    {
        VerifyEmail::createUrlUsing(function (object $notifiable): string {
            if ($notifiable instanceof CentralUser) {
                return URL::temporarySignedRoute(
                    'central.auth.verification.verify',
                    Carbon::now()->addMinutes(60),
                    [
                        'id' => $notifiable->getKey(),
                        'hash' => sha1($notifiable->getEmailForVerification()),
                    ]
                );
            }

            $parameters = [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ];

            if (! $notifiable instanceof TenantUser) {
                return URL::temporarySignedRoute(
                    'tenant.auth.verification.verify',
                    Carbon::now()->addMinutes(60),
                    $parameters,
                );
            }

            $tenantHost = tenant()?->domains()->value('domain')
                ?? request()->getHost();

            if (! is_string($tenantHost) || $tenantHost === '') {
                return URL::temporarySignedRoute(
                    'tenant.auth.verification.verify',
                    Carbon::now()->addMinutes(60),
                    $parameters,
                );
            }

            $previousRoot = config('app.url');
            $scheme = parse_url((string) $previousRoot, PHP_URL_SCHEME) ?: 'http';
            URL::forceRootUrl($scheme.'://'.$tenantHost);

            try {
                return URL::temporarySignedRoute(
                    'tenant.auth.verification.verify',
                    Carbon::now()->addMinutes(60),
                    $parameters,
                );
            } finally {
                URL::forceRootUrl($previousRoot);
            }
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('central-auth', function (Request $request): Limit {
            return $this->authRateLimit($request->ip());
        });

        RateLimiter::for('tenant-auth', function (Request $request): Limit {
            $tenantKey = tenant('id') ?? 'central';

            return $this->authRateLimit($tenantKey.'|'.$request->ip());
        });

        RateLimiter::for('central-api', function (Request $request): Limit {
            return Limit::perMinute(60)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('tenant-api', function (Request $request): Limit {
            $tenantKey = tenant('id') ?? 'central';
            $actor = $request->user()?->getAuthIdentifier() ?? $request->ip();
            $perMinute = app(TenantApiQuotaService::class)->requestsPerMinute();

            return Limit::perMinute($perMinute)->by($tenantKey.'|'.$actor);
        });
    }

    private function authRateLimit(string $key): Limit
    {
        return app()->environment('testing')
            ? Limit::none()
            : Limit::perMinute(5)->by($key);
    }

    private function configureApiDocumentation(): void
    {
        Gate::define('viewApiDocs', fn (?object $user = null): bool => app()->environment(['local', 'testing']));

        Scramble::configure()
            ->routes(fn (Route $route): bool => $this->isCentralApiRoute($route))
            ->expose(
                ui: '/docs/central',
                document: '/docs/central.json',
            );

        Scramble::registerApi('tenant', [
            'info' => [
                'description' => <<<'MD'
# Tenant API

Tenant-scoped SaaS endpoints. Resolve the tenant by domain (Stancl identification), then authenticate with a **tenant** Sanctum personal access token.
MD,
            ],
        ])
            ->routes(fn (Route $route): bool => $this->isTenantApiRoute($route))
            ->expose(
                ui: '/docs/tenant',
                document: '/docs/tenant.json',
            );
    }

    private function isCentralApiRoute(Route $route): bool
    {
        if (Str::contains((string) $route->getName(), 'test.')) {
            return false;
        }

        $controller = $route->getControllerClass();

        if (is_string($controller) && Str::startsWith($controller, 'App\\Http\\Controllers\\Central\\')) {
            return true;
        }

        return $route->getName() === 'central.health';
    }

    private function isTenantApiRoute(Route $route): bool
    {
        $controller = $route->getControllerClass();

        if (is_string($controller) && Str::startsWith($controller, 'App\\Http\\Controllers\\Tenant\\')) {
            return true;
        }

        return $route->getName() === 'tenant.health';
    }
}
