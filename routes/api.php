<?php

declare(strict_types=1);

use App\Http\Controllers\Central\ActivityController;
use App\Http\Controllers\Central\AuthController;
use App\Http\Controllers\Central\CouponController;
use App\Http\Controllers\Central\DomainController;
use App\Http\Controllers\Central\FeatureFlagController;
use App\Http\Controllers\Central\InvoiceController;
use App\Http\Controllers\Central\PlanController;
use App\Http\Controllers\Central\PlatformSettingController;
use App\Http\Controllers\Central\SubscriptionController;
use App\Http\Controllers\Central\TenantController;
use App\Http\Controllers\Central\TenantImpersonationController;
use App\Http\Controllers\Central\TenantOpsController;
use App\Http\Controllers\Central\UserController;
use App\Http\Controllers\Central\WebhookController;
use App\Http\Controllers\Central\WebhookEventController;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central API Routes
|--------------------------------------------------------------------------
|
| Landlord / platform API endpoints. Bound to central domain(s) only.
|
*/

foreach (config('tenancy.identification.central_domains') as $domain) {
    Route::domain($domain)->group(function (): void {
        Route::get('/health', function () {
            $data = [
                'context' => 'central',
                'tenancy_initialized' => tenancy()->initialized,
            ];

            if (! app()->isProduction()) {
                $data['environment'] = app()->environment();
            }

            return ApiResponse::success(
                data: $data,
                message: 'Central API is healthy.',
            );
        })->middleware('throttle:60,1')->name('central.health');

        Route::prefix('auth')->name('central.auth.')->group(function (): void {
            Route::post('login', [AuthController::class, 'login'])
                ->middleware('throttle:central-auth')
                ->name('login');

            Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
                ->middleware('throttle:central-auth')
                ->name('password.email');

            Route::post('reset-password', [AuthController::class, 'resetPassword'])
                ->middleware('throttle:central-auth')
                ->name('password.update');

            Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');

            Route::middleware(['auth:sanctum', 'throttle:central-api'])->group(function (): void {
                Route::get('me', [AuthController::class, 'me'])->name('me');
                Route::put('profile', [AuthController::class, 'updateProfile'])->name('profile.update');
                Route::put('password', [AuthController::class, 'changePassword'])->name('password.change');
                Route::post('email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
                    ->middleware('throttle:6,1')
                    ->name('verification.send');
                Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            });
        });

        Route::post('/webhooks/billing/{gateway}', WebhookController::class)
            ->middleware('throttle:60,1')
            ->name('central.webhooks.billing');

        Route::middleware(['auth:sanctum', 'throttle:central-api'])->group(function (): void {
            Route::apiResource('tenants', TenantController::class)->names('central.tenants');
            Route::post('tenants/{tenant}/impersonate', [TenantImpersonationController::class, 'store'])
                ->name('central.tenants.impersonate');
            Route::get('tenants/{tenant}/ops-summary', [TenantOpsController::class, 'show'])
                ->name('central.tenants.ops-summary');
            Route::apiResource('tenants.domains', DomainController::class)
                ->except(['show'])
                ->scoped()
                ->middlewareFor('store', 'entitlement:domains.max')
                ->names('central.tenants.domains');

            Route::apiResource('plans', PlanController::class)->names('central.plans');
            Route::apiResource('coupons', CouponController::class)->names('central.coupons');
            Route::apiResource('users', UserController::class)->names('central.users');

            Route::get('settings', [PlatformSettingController::class, 'index'])->name('central.settings.index');
            Route::put('settings', [PlatformSettingController::class, 'upsert'])->name('central.settings.upsert');
            Route::get('settings/{setting}', [PlatformSettingController::class, 'show'])
                ->where('setting', '[\w.-]+')
                ->name('central.settings.show');
            Route::delete('settings/{setting}', [PlatformSettingController::class, 'destroy'])
                ->where('setting', '[\w.-]+')
                ->name('central.settings.destroy');

            Route::get('feature-flags', [FeatureFlagController::class, 'index'])->name('central.feature-flags.index');
            Route::put('feature-flags', [FeatureFlagController::class, 'upsert'])->name('central.feature-flags.upsert');

            Route::get('activity', [ActivityController::class, 'index'])->name('central.activity.index');
            Route::get('activity/{activity}', [ActivityController::class, 'show'])->name('central.activity.show');

            Route::get('webhook-events', [WebhookEventController::class, 'index'])->name('central.webhook-events.index');
            Route::get('webhook-events/{webhookEvent}', [WebhookEventController::class, 'show'])->name('central.webhook-events.show');

            Route::get('tenants/{tenant}/subscription', [SubscriptionController::class, 'show'])
                ->name('central.tenants.subscription.show');
            Route::get('tenants/{tenant}/entitlements', [SubscriptionController::class, 'entitlements'])
                ->name('central.tenants.entitlements');
            Route::post('tenants/{tenant}/subscription', [SubscriptionController::class, 'store'])
                ->name('central.tenants.subscription.store');
            Route::post('tenants/{tenant}/subscription/cancel', [SubscriptionController::class, 'cancel'])
                ->name('central.tenants.subscription.cancel');
            Route::post('tenants/{tenant}/subscription/resume', [SubscriptionController::class, 'resume'])
                ->name('central.tenants.subscription.resume');
            Route::post('tenants/{tenant}/subscription/change-plan', [SubscriptionController::class, 'changePlan'])
                ->name('central.tenants.subscription.change-plan');
            Route::post('tenants/{tenant}/subscription/suspend', [SubscriptionController::class, 'suspend'])
                ->name('central.tenants.subscription.suspend');
            Route::get('tenants/{tenant}/subscription/history', [SubscriptionController::class, 'history'])
                ->name('central.tenants.subscription.history');

            Route::get('tenants/{tenant}/invoices', [InvoiceController::class, 'index'])
                ->name('central.tenants.invoices.index');
            Route::get('tenants/{tenant}/payments', [InvoiceController::class, 'payments'])
                ->name('central.tenants.payments.index');
            Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])
                ->name('central.invoices.show');
        });

        if (app()->environment('testing')) {
            Route::post('/__test/validation', function () {
                request()->validate([
                    'email' => ['required', 'email'],
                ]);

                return ApiResponse::success(message: 'Validated.');
            })->name('central.test.validation');

            Route::get('/__test/not-found', function () {
                abort(404, 'Custom not found message.');
            })->name('central.test.not-found');

            Route::get('/__test/forbidden', function () {
                abort(403, 'Custom forbidden message.');
            })->name('central.test.forbidden');
        }
    });
}
