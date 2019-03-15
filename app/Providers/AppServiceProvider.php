<?php

namespace App\Providers;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

use App\Services\FindTaxiDriverInterface;
use App\Services\NoDriverFoundException;
use App\Services\TransactionCancelException;
use App\Services\FindTaxiDriverService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(FindTaxiDriverInterface::class, function () {
            return new FindTaxiDriverService();
        });
    }
}
