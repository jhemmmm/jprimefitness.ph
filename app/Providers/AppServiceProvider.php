<?php

namespace App\Providers;

use App\Models\BusinessProfile;
use App\Models\PTProduct;
use App\Models\RatePlan;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
