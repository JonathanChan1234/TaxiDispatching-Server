<?php

namespace App\Observers;

use App\Transcation;
use App\Events\TransactionUpdateEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TranscationObserver
{
    /**
     * Handle the transcation "created" event.
     *
     * @param  \App\Transcation  $transcation
     * @return void
     */
    public function created(Transcation $transcation)
    {
        Log::info('created');
        event(new TransactionUpdateEvent($transcation));
    }

    /**
     * Handle the transcation "updated" event.
     *
     * @param  \App\Transcation  $transcation
     * @return void
     */
    public function updated(Transcation $transcation)
    {
        Log::info('updated');
        event(new TransactionUpdateEvent($transcation));
    }

    /**
     * Handle the transcation "deleted" event.
     *
     * @param  \App\Transcation  $transcation
     * @return void
     */
    public function deleted(Transcation $transcation)
    {
        Log::info('deleted');
    }

    /**
     * Handle the transcation "restored" event.
     *
     * @param  \App\Transcation  $transcation
     * @return void
     */
    public function restored(Transcation $transcation)
    {
        //
    }

    /**
     * Handle the transcation "force deleted" event.
     *
     * @param  \App\Transcation  $transcation
     * @return void
     */
    public function forceDeleted(Transcation $transcation)
    {
        //
    }
}
