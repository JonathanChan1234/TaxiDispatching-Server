<?php

namespace App\Observers;

use App\RideShare;
use App\Events\UpdateEvent;

class RideShareObserver
{
    /**
     * Handle the ride share "created" event.
     *
     * @param  \App\RideShare  $rideShare
     * @return void
     */
    public function created(RideShare $rideShare)
    {
        event(new UpdateEvent('shareRide'));
    }

    /**
     * Handle the ride share "updated" event.
     *
     * @param  \App\RideShare  $rideShare
     * @return void
     */
    public function updated(RideShare $rideShare)
    {
        event(new UpdateEvent('shareRide'));
    }

    /**
     * Handle the ride share "deleted" event.
     *
     * @param  \App\RideShare  $rideShare
     * @return void
     */
    public function deleted(RideShare $rideShare)
    {
        //
    }

    /**
     * Handle the ride share "restored" event.
     *
     * @param  \App\RideShare  $rideShare
     * @return void
     */
    public function restored(RideShare $rideShare)
    {
        //
    }

    /**
     * Handle the ride share "force deleted" event.
     *
     * @param  \App\RideShare  $rideShare
     * @return void
     */
    public function forceDeleted(RideShare $rideShare)
    {
        //
    }
}
