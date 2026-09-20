<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\BusinessProfile;
use App\Models\CashAdvance;
use App\Models\InventoryItem;
use App\Models\MemberPtSessionUsage;
use App\Models\PTProduct;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\RatePlan;
use App\Models\Role;
use App\Models\SaleTransaction;
use App\Observers\AttendanceObserver;
use App\Observers\BusinessProfileObserver;
use App\Observers\CashAdvanceObserver;
use App\Observers\InventoryItemObserver;
use App\Observers\MemberPtSessionUsageObserver;
use App\Observers\PayoutObserver;
use App\Observers\PayrollObserver;
use App\Observers\SaleTransactionObserver;
use App\Services\Sync\SpatiePivotOutboxListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->mixedCase()->symbols());

        $this->forceHttpsUrlSchemeWhenConfigured();

        Attendance::observe(AttendanceObserver::class);
        BusinessProfile::observe(BusinessProfileObserver::class);
        CashAdvance::observe(CashAdvanceObserver::class);
        InventoryItem::observe(InventoryItemObserver::class);
        MemberPtSessionUsage::observe(MemberPtSessionUsageObserver::class);
        Payout::observe(PayoutObserver::class);
        Payroll::observe(PayrollObserver::class);
        SaleTransaction::observe(SaleTransactionObserver::class);

        Event::listen(RoleAttachedEvent::class, [SpatiePivotOutboxListener::class, 'handleRoleAttached']);
        Event::listen(RoleDetachedEvent::class, [SpatiePivotOutboxListener::class, 'handleRoleDetached']);
        Event::listen(PermissionAttachedEvent::class, [SpatiePivotOutboxListener::class, 'handlePermissionAttached']);
        Event::listen(PermissionDetachedEvent::class, [SpatiePivotOutboxListener::class, 'handlePermissionDetached']);

        View::composer('panel.*', function ($view) {
            $businessProfile = BusinessProfile::current();
            $ratePlans = RatePlan::membership()->orderBy('duration_days')->orderBy('name')->get();
            $ptProducts = PTProduct::where('is_active', true)->get();
            // Roles the signed-in user may assign from the employees UI, and every role's badge colour for the roleBadge filter.
            $roles = auth()->check() ? Role::assignableBy(auth()->user())->makeHidden('permissions') : collect();
            $roleColors = Role::query()->pluck('color', 'name');

            $view->with(compact('businessProfile', 'ratePlans', 'ptProducts', 'roles', 'roleColors'));
        });

        View::composer('home.*', function ($view) {
            $view->with([
                'businessProfile' => BusinessProfile::current(),
                'ratePlans' => RatePlan::query()
                    ->where('is_active', true)
                    ->whereNotNull('price')
                    ->orderBy('duration_days')
                    ->orderBy('name')
                    ->get(),
                'ptProducts' => PTProduct::query()
                    ->where('is_active', true)
                    ->whereNotNull('price')
                    ->orderBy('session_count')
                    ->orderBy('name')
                    ->get(),
            ]);
        });
    }

    /**
     * Force generated URLs to use HTTPS when the app URL is HTTPS.
     *
     * @return void
     */
    private function forceHttpsUrlSchemeWhenConfigured(): void
    {
        $appUrl = (string) config('app.url');

        if (parse_url($appUrl, PHP_URL_SCHEME) !== 'https') {
            return;
        }

        URL::forceRootUrl(rtrim($appUrl, '/'));
        URL::forceScheme('https');
    }
}
