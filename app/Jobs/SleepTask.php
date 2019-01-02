<?php

namespace App\Jobs;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use App\Transcation;

class SleepTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $transcation;
    
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Transcation $transcation)
    {
        Log::info("Job constructured");
        $this->transcation = $transcation;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("Job handled");
        sleep(5);
        Log::info("Job finished");
    }
}
