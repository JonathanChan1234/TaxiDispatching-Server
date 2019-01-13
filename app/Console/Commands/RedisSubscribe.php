<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

use App\Transcation;
use App\Driver;
use App\Jobs\TransactionProcessor;
use App\Jobs\PassengerTimeoutJob;

class RedisSubscribe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:subscribe';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscription to redis account';

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
        //handle the driver response
        //message: {response: 0/1, transcation: id, driver:id}
        Redis::subscribe(['driverResponse'], function($message) {
            $data = json_decode($message, true);
            print_r("Driver Response");
            print_r($data);
            $transcation = Transcation::find($data['transcation']);
            $driver = Driver::find($data['driver']);

            if($data['response'] == 1) {
                // update the status of the driver    
                $transcation->status = 102;
                $transcation->save();
                PassengerTimeoutJob::dispatch($transcation, $driver)->delay(now()->addMinutes(5));
            } else {
                // restore the driver states
                $driver->status = 0;
                $driver->transcation_id = 0;
                $driver->save();

                $transcation->status = 100;
                $transcation->save();
                
                //Resume the searching process again
                TransactionProcessor::dispatch($transcation);
            }
        });
    }
}
