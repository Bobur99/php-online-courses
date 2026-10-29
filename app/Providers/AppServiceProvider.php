<?php

namespace App\Providers;

use App\Contracts\PaymentProviderInterface;
use App\Enums\PermissionName;
use App\Models\User;
use App\Services\HttpPaymentProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            PaymentProviderInterface::class,
            HttpPaymentProvider::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user): ?bool => $user->is_blocked ? false : null);

        foreach (PermissionName::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }
    }
}
