<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\RatePlan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('panel.*', function ($view) {
            $user = auth()->user();

            // Get branches accessible to the user
            $branches = $user->hasRole('super admin')
                ? Branch::where('status', Branch::STATUS_OPEN)->get()
                : $user->branches()->where('status', Branch::STATUS_OPEN)->get();

            // Get all rate plans
            $ratePlans = RatePlan::where('is_active', true)->get();

            // Get all roles
            $roles = Role::all();

            $view->with(compact('branches', 'ratePlans', 'roles'));
        });
    }
}
