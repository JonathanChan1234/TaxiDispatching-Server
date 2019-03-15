<?php

namespace App\Jobs;

use App\Driver;
use App\RideShareTransaction;
use App\Jobs\RideSharingProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatch 5 minutes after the driver is found whne
 */
class RideShareDriverTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $sharedTransaction;
    protected $driver;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Driver $driver, RideShareTransaction $sharedTransaction)
    {
        $this->sharedTransaction = $sharedTransaction;
        $this->driver = $driver;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Status 102 : Driver confirm the deal and wait for confirmation
        if ($this->sharedTransaction->status != 102) {
            // Restore the driver status
            $this->driver->occupied = 0;
            $this->driver->transcation_id = 0;
            $this->driver->save();

            // Cancel the ride transaction 
            $this->sharedTransaction->status = 400;
            
            // Restore the status of the transactions
            $first_transaction = Transcation::find($this->sharedTransaction->first_transaction);
            $second_transaction = Transcation::find($this->sharedTransaction->second_transaction);
            $first_transaction->status = 100; 
            $second_transaction->status = 100;
            $first_transaction->save();
            $second_transaction->save();

            // Process the share ride again
            RideSharingProcessor::dispatch($first_transaction);
            RideSharingProcessor::dispatch($second_transaction);
        }
    }
}
