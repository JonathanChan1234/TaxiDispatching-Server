<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

use App\Transcation;
use App\Driver;
use App\Events\DriverNotificationEvent;
use App\Utility\FCMHelper;

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
        Log::info("Handling passenger timeout event Personal Ride ID: ". $this->transcation->id);
        if($this->transcation->status == 102) {
            Log::info("Passenger timeout ");
            // Cancel the transaction
            $this->transcation->status = 400; //Cancel status
            $this->transcation->cancelled = 1; //Cancel status
            $this->transcation->driver_id = 0;
            $this->transcation->save();

            event(new DriverNotificationEvent($this->driver, $this->transcation, "PassengerTimeout"));
            if($this->driver->fcm_token != null) {
                FCMHelper::pushMessageToUser($this->driver->fcm_token,
                "No response from the passenger", [
                    'driver' => 'transcation'
                ]);
            }
        }
    }
}
