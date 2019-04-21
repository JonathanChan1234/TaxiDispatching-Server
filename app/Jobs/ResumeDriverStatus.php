<?php

namespace App\Jobs;

use App\Driver;
use App\Jobs\TransactionProcessor;
use App\Transcation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Timeout event
 * If the driver does not respond in the time limit (3 minutes)
 * The server will search for another drivers
 */
class ResumeDriverStatus implements ShouldQueue
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
        Log::Info("Handling Resume driver status " . $this->transcation->id);
        Log::Info("Handling Resume driver status: transaction status " . $this->transcation->status);
        
        if ($this->transcation->status == 101 &&
            $this->transcation->driver_id == $this->driver->id) {
            Log::Info("Resume driver status: personal ride id " . $this->transcation->id);
            Log::Info("Resume driver status:  Driver Timeout" );
            //Restore the transcation status to 100
            $this->transcation->driver_id = 0;
            $this->transcation->status = 100;
            $this->transcation->save();

            //Restore the driver status
            $this->driver->occupied = 0;
            $this->driver->transcation_id = 0;
            $this->driver->save();

            //Search for another driver
            TransactionProcessor::dispatch($this->transcation);
        }
    }
}
