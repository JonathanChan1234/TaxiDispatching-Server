<?php

namespace App\Jobs;

use App\Driver;
use App\Transcation;
use App\RideShareTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RideSharePassengerTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $driver, $transcation;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Driver $driver, RideShareTransaction $transcation)
    {
        $this->driver = $driver;
        $this->transcation = $transcation;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($transcation->status != 200) {
            // Free the driver
            $this->driver->occupied = 0;
            $this->driver->transcation_id = 0;
            $this->driver->save();
            
            $state;
            // Transaction cancelled
            if($this->transcation->first_confirmed != 200) {    //First transaction is cancelled
                
            }
            if($this->transcation->second_confirmed != 200) {   //Second transaction is cancelled

            }

             // Transaction cancelled
            

            // Find the driver and passengers

        }       
    }
}
