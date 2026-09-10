<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\Import;
use App\Models\User;
use App\Policies\CustomerActivityPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends AuthServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Customer::class => CustomerPolicy::class,
        User::class => UserPolicy::class,
        CustomerActivity::class => CustomerActivityPolicy::class,
    ];

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
        $this->registerPolicies();

        // Force Arabic locale to ensure RTL direction is applied in Filament
        app()->setLocale('ar');

        // Admin-only gate for system-wide access.
        Gate::define('admin-access', fn (User $user) => $user->isAdmin());

        // Gate for import access (admin and team leader).
        Gate::define('import-customers', fn (User $user) => $user->isAdmin() || $user->isTeamLeader());

        // Gate for viewing audit logs (admin only).
        Gate::define('view-audit-logs', fn (User $user) => $user->isAdmin());

        // Gate for managing lookup tables (admin only).
        Gate::define('manage-lookups', fn (User $user) => $user->isAdmin());
    }
}
