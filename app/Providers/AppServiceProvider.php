<?php

namespace App\Providers;

use App\User;
use App\Transcation;
use App\RideShare;
use App\RideShareTransaction;

use App\Observers\TranscationObserver;
use App\Observers\DriverObserver;
use App\Observers\RideShareObserver;
use App\Observers\RideShareTransactionObserver;


use App\Services\FindTaxiDriver\FindTaxiDriverInterface;
use App\Services\FindTaxiDriver\NoDriverFoundException;
use App\Services\FindTaxiDriver\TransactionCancelException;
use App\Services\FindTaxiDriver\FindTaxiDriverService;

use App\Services\RatingService\AddRatingInterface;
use App\Services\RatingService\AddRatingService;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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
        User::observe(UserObserver::class);
        Transcation::observe(TranscationObserver::class);
        RideShare::observe(RideShareObserver::class);
        RideShareTransaction::observe(RideShareTransactionObserver::class);
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
        $this->app->singleton(AddRatingInterface::class, function() {
            return new AddRatingService();
        });
    }
}
