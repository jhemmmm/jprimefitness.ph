<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\BranchCashLedgerEntry;
use App\Models\RatePlan;
use App\Models\User;
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
            $branchesQuery = $user->hasRole('super admin')
                ? Branch::query()->where('status', Branch::STATUS_OPEN)
                : $user->branches()->where('status', Branch::STATUS_OPEN);

            $branches = $branchesQuery
                ->withSum([
                    'cashLedgerEntries as cash_in_total' => fn ($query) => $query->where('direction', BranchCashLedgerEntry::DIRECTION_IN),
                ], 'amount')
                ->withSum([
                    'cashLedgerEntries as cash_out_total' => fn ($query) => $query->where('direction', BranchCashLedgerEntry::DIRECTION_OUT),
                ], 'amount')
                ->orderBy('name')
                ->get()
                ->map(function (Branch $branch) {
                    $cashInTotal = round((float) ($branch->cash_in_total ?? 0), 2);
                    $cashOutTotal = round((float) ($branch->cash_out_total ?? 0), 2);

                    $branch->setAttribute('cash_in_total', $cashInTotal);
                    $branch->setAttribute('cash_out_total', $cashOutTotal);
                    $branch->setAttribute('cash_balance', round($cashInTotal - $cashOutTotal, 2));

                    return $branch;
                })
                ->values();

            // Get all rate plans
            $ratePlans = RatePlan::where('is_active', true)->get();

            // Get all roles
            $roles = Role::all();

            $view->with(compact('branches', 'ratePlans', 'roles'));
        });
    }
}
