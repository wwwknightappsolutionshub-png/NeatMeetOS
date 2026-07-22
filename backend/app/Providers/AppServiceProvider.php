<?php

namespace App\Providers;

use App\Domains\Integrations\Contracts\PaymentProviderAttemptContract;
use App\Domains\Integrations\Services\ProviderDispatchBridge;
use App\Domains\Inventory\Services\InventoryConsumptionService;
use App\Domains\Memberships\Services\EntitlementResolutionService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\Assemblers\DepositLifecycleMapper;
use App\Shared\Commerce\Assemblers\InventoryConsumptionRequestBuilder;
use App\Shared\Commerce\Contracts\CheckoutImportFromBookingContract;
use App\Shared\Commerce\Contracts\DepositSettlementContract;
use App\Shared\Commerce\Contracts\EntitlementResolutionContract;
use App\Shared\Commerce\Contracts\PaymentAllocationContract;
use App\Shared\Commerce\Contracts\StockConsumptionExecutionContract;
use App\Shared\Commerce\Contracts\StockConsumptionRequestContract;
use App\Shared\Commerce\Services\BookingCommerceImportService;
use App\Shared\Commerce\Services\PaymentAllocationValidator;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(AuditLogger::class);

        $this->app->singleton(DepositSettlementContract::class, DepositLifecycleMapper::class);
        $this->app->singleton(EntitlementResolutionContract::class, EntitlementResolutionService::class);
        $this->app->singleton(PaymentAllocationContract::class, PaymentAllocationValidator::class);
        $this->app->singleton(StockConsumptionRequestContract::class, InventoryConsumptionRequestBuilder::class);
        $this->app->singleton(StockConsumptionExecutionContract::class, InventoryConsumptionService::class);
        $this->app->singleton(CheckoutImportFromBookingContract::class, BookingCommerceImportService::class);
        $this->app->singleton(PaymentProviderAttemptContract::class, ProviderDispatchBridge::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('public-book', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('public-book-write', function (Request $request) {
            return Limit::perMinute(12)->by($request->ip());
        });

        RateLimiter::for('public-join', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('public-member', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('public-signup', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('public-webhooks', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip().'|'.($request->route('driver') ?? 'webhook'));
        });

        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(15)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });
    }
}
