<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use Throwable;

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
        $this->ensureStorageLinkExists();
        $this->registerSuperAdminGateBypass();
        $this->registerPasswordDefaults();
        $this->registerPolicies();
        $this->registerTailAdminComponents();
        $this->enforceHttpsInProduction();
    }

    /**
     * Create the public/storage symlink when it is missing so uploaded files
     * (brand logos, product photos, purchase documents) render right after a
     * fresh clone, without requiring a manual `php artisan storage:link`.
     */
    private function ensureStorageLinkExists(): void
    {
        if (file_exists(public_path('storage')) || ! is_dir(storage_path('app/public'))) {
            return;
        }

        // Only attempt occasionally: environments that forbid symlinks must
        // never pay the cost on every request.
        if (! Cache::add('storage_link_attempted', true, 21600)) {
            return;
        }

        try {
            Artisan::call('storage:link');
        } catch (Throwable) {
            // A missing link degrades gracefully (thumbnails fall back to
            // initials), so never let it break the application.
        }
    }

    /**
     * Force HTTPS in production so all generated URLs and assets are secure.
     */
    private function enforceHttpsInProduction(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Register Blade component aliases for TailAdmin modules.
     */
    private function registerTailAdminComponents(): void
    {
        $componentPaths = [
            'dashboard' => 'views/components/main/dashboard',
            'ecommerce' => 'views/components/main/dashboard/ecommerce',
            'form' => 'views/components/main/forms',
            'profile' => 'views/components/main/profile',
            'tables' => 'views/components/main/tables',
            'calendar' => 'views/components/main/calendar',
        ];

        foreach ($componentPaths as $prefix => $path) {
            $fullPath = resource_path($path);
            if (is_dir($fullPath)) {
                Blade::anonymousComponentPath($fullPath, $prefix);
            }
        }
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
