<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\BusinessProfile;
use App\Models\InventoryItem;
use App\Models\MemberPtSessionUsage;
use App\Models\PTProduct;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\RatePlan;
use App\Observers\AttendanceObserver;
use App\Observers\BusinessProfileObserver;
use App\Observers\InventoryItemObserver;
use App\Observers\MemberPtSessionUsageObserver;
use App\Observers\PayoutObserver;
use App\Observers\PayrollObserver;
use App\Services\Sync\SpatiePivotOutboxListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Attendance::observe(AttendanceObserver::class);
        BusinessProfile::observe(BusinessProfileObserver::class);
        InventoryItem::observe(InventoryItemObserver::class);
        MemberPtSessionUsage::observe(MemberPtSessionUsageObserver::class);
        Payout::observe(PayoutObserver::class);
        Payroll::observe(PayrollObserver::class);

        Event::listen(RoleAttachedEvent::class, [SpatiePivotOutboxListener::class, 'handleRoleAttached']);
        Event::listen(RoleDetachedEvent::class, [SpatiePivotOutboxListener::class, 'handleRoleDetached']);
        Event::listen(PermissionAttachedEvent::class, [SpatiePivotOutboxListener::class, 'handlePermissionAttached']);
        Event::listen(PermissionDetachedEvent::class, [SpatiePivotOutboxListener::class, 'handlePermissionDetached']);

        View::composer('panel.*', function ($view) {
            $businessProfile = BusinessProfile::current();
            $ratePlans = RatePlan::where('is_active', true)->get();
            $ptProducts = PTProduct::where('is_active', true)->get();
            $roles = Role::all();

            $view->with(compact('businessProfile', 'ratePlans', 'ptProducts', 'roles'));
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
}
