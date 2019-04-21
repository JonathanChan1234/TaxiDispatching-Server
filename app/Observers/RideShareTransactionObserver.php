<?php

namespace App\Observers;

use App\RideShareTransaction;
use App\Events\UpdateEvent;

class RideShareTransactionObserver
{
    /**
     * Handle the ride share transaction "created" event.
     *
     * @param  \App\RideShareTransaction  $rideShareTransaction
     * @return void
     */
    public function created(RideShareTransaction $rideShareTransaction)
    {
        event(new UpdateEvent('shareRideTransaction'));
    }

    /**
     * Handle the ride share transaction "updated" event.
     *
     * @param  \App\RideShareTransaction  $rideShareTransaction
     * @return void
     */
    public function updated(RideShareTransaction $rideShareTransaction)
    {
        event(new UpdateEvent('shareRideTransaction'));
    }

    /**
     * Handle the ride share transaction "deleted" event.
     *
     * @param  \App\RideShareTransaction  $rideShareTransaction
     * @return void
     */
    public function deleted(RideShareTransaction $rideShareTransaction)
    {
        //
    }

    /**
     * Handle the ride share transaction "restored" event.
     *
     * @param  \App\RideShareTransaction  $rideShareTransaction
     * @return void
     */
    public function restored(RideShareTransaction $rideShareTransaction)
    {
        //
    }

    /**
     * Handle the ride share transaction "force deleted" event.
     *
     * @param  \App\RideShareTransaction  $rideShareTransaction
     * @return void
     */
    public function forceDeleted(RideShareTransaction $rideShareTransaction)
    {
        //
    }
}
