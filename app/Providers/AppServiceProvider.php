<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
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
        $this->registerSuperAdminGateBypass();
        $this->registerPasswordDefaults();
        $this->registerPolicies();
    }

    /**
     * Register policies for models Laravel cannot auto-discover (those living
     * outside the App\Models namespace, such as Spatie's Role model).
     */
    private function registerPolicies(): void
    {
        Gate::policy(Role::class, RolePolicy::class);
    }

    /**
     * Grant the Super Admin role every ability without needing to keep its
     * permission set in sync as new modules are introduced.
     */
    private function registerSuperAdminGateBypass(): void
    {
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });
    }

    /**
     * Enforce a consistent minimum password policy for all password changes,
     * resets, and user provisioning.
     */
    private function registerPasswordDefaults(): void
    {
        Password::defaults(fn () => Password::min(8)->letters()->numbers()->symbols());
    }
}
