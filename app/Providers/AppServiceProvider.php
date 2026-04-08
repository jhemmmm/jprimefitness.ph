<?php

namespace App\Providers;

use App\Models\PTProduct;
use App\Models\RatePlan;
use App\Services\BusinessProfileContext;
use App\Services\CashLedgerService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(BusinessProfileContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('panel.*', function ($view) {
            $businessProfile = app(BusinessProfileContext::class)->profile();
            $cashLedgerSummary = app(CashLedgerService::class)->summarize();
            $businessProfile->setAttribute('cash_ledger_summary', $cashLedgerSummary);
            $businessProfile->setAttribute('cash_balance', $cashLedgerSummary['balance']);
            $branches = collect([[
                'id' => $businessProfile->id,
                'name' => $businessProfile->name,
                'city' => $businessProfile->city,
                'province' => $businessProfile->province,
                'status' => $businessProfile->status,
            ]]);

            $ratePlans = RatePlan::where('is_active', true)->get();
            $ptProducts = PTProduct::where('is_active', true)->get();
            $roles = Role::all();

            $view->with(compact('businessProfile', 'branches', 'ratePlans', 'ptProducts', 'roles'));
        });

        View::composer('home.*', function ($view) {
            $view->with([
                'businessProfile' => app(BusinessProfileContext::class)->profile(),
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
