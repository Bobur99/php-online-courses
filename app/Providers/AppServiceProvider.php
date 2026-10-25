<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\PaymentProviderInterface;
use App\Services\HttpPaymentProvider;

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
        //
    }
}
