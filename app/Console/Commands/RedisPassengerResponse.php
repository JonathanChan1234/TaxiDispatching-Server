<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

use App\Transcation;
use App\Driver;
use App\Jobs\TransactionProcessor;
use App\Jobs\PassengerTimeoutJob;

class RedisPassengerResponse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:passenger';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Redis::subscribe(['passengerResponse'], function($message) {
            $data = json_decode($message, true);
            print_r("Passenger Response");
            print_r($data);
            if($data['response'] == 1) {
                // update the status of the driver and transaction
                $transcation = Transcation::find($data['transcation']);
                if($transcation->status != 400) {
                    $transcation->status = 200;
                    $transcation->driver_id = $data['driver'];
                    $transcation->save();
                }
            } else {
                // restore the driver status
                $driver = Driver::find($data['driver']);
                $driver->occupied = 0;
                $driver->transcation_id = 0;
                $driver->save();
                
                //Resume the searching process again
                $transcation = Transcation::find($data['transcation']);
                TransactionProcessor::dispatch($transcation);
            }
        });
    }
}
