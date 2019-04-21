<?php

namespace App\Jobs;

use App\Driver;
use App\RideShareTransaction;
use App\Utility\FCMHelper;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DriverShareRideReachJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $transaction, $ride;

    /**
     * Create a new job instance.
     * $ride: first/second transaction
     * @return void
     */
    public function __construct(RideShareTransaction $transaction, $ride)
    {
        $this->transaction = $transaction;
        $this->ride = $ride;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if($this->ride == 'first') {
            $this->transaction->first_confirmed = 102;
        } else {
            $this->transaction->second_confirmed = 102;
        }
        $this->transaction->save();
        $driver = Driver::find($this->transaction->driver_id);
        if($driver->fcm_token != null) {
            FCMHelper::pushMessageToUser($driver->fcm_token,
            "",
            ['driver' => 'rideshare']);
        }
    }
}
