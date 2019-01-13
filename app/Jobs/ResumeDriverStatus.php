<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use App\Transcation;
use App\Driver;
use App\Jobs\TransactionProcessor;
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
        if($this->transcation->status == 101) {
            //Restore the transcation status to 100
            $this->transcation->status = 100;
            $this->transcation->save();

            //Restore the driver status
            $this->driver->occupied = 0;
            $this->driver->transcation_id = 0;
            $this->driver->save();
            TransactionProcessor::dispatch($this->transcation);
        }
    }
}
