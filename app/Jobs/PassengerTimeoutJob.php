<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use App\Transcation;
use App\Driver;
use App\Events\DriverNotificationEvent;

/**
 * Timeout event 
 * When the passenger did not respond within the time limit
 * Restore the driver status and cancel the transaction
 */
class PassengerTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $transcation, $driver;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Transcation $transcation, Driver $driver)
    {
        $this->transcation = $transcation;
        $this->driver = $driver;   
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if($this->transcation->status == 102) {
            // Cancel the transaction
            $this->transcation->status = 400; //Cancel status
            $this->transcation->driver_id = 0;
            $this->transcation->save();

            // Restore the driver status
            $this->driver->occupied = 0;
            $this->driver->transcation_id = 0;
            $this->driver->save();
            event(new DriverNotificationEvent($this->driver, $this->transcation, "PassengerTimeout"));
        }
    }
}
