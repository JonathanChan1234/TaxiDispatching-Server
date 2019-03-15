<?php

namespace App\Console\Commands;

use App\Driver;
use App\Events\PassengerNotificationEvent;
use App\Events\ShareRideDriverEvent;
use App\Http\Resources\RideShareTransactionResource;
use App\Jobs\PassengerTimeoutJob;
use App\Jobs\RideSharingProcessor;
use App\Jobs\TransactionProcessor;
use App\RideShareTransaction;
use App\Transcation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

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
        Redis::psubscribe(['*'], function ($message, $channel) {
            echo 'channel: ' . $channel;
            switch ($channel) {
                case 'driverResponse':
                    $this->updateDriverStatus($message);
                    break;
                case 'passengerResponse':
                    $this->updatePassengerStatus($message);
                    break;
                case 'shareRideDriverResponse':
                    $this->updateShareRideDriverStatus($message);
                    break;
                case 'shareRidePassengerResponse':
                    $this->updateShareRidePassengerStatus($message);
                default:
                    break;
            }
        });
    }

    public function updateShareRidePassengerStatus($message)
    {
        $data = json_decode($message, true);
        print_r("Share Ride Passenger Response");
        print_r($data);
        $shareRideTranscation = RideShareTransaction::find($data['shareRideTranscation']);
        $first_transcation = Transcation::find($shareRideTranscation->first_transaction);
        $second_transcation = Transcation::find($shareRideTranscation->second_transaction);
        $driver = Driver::find($shareRideTranscation->driver_id);
        $transcation = Transcation::find($data['transcation']);
        if ($data['response'] == 1) {
            if ($shareRideTranscation->first_transaction == $data['transcation']) {
                $shareRideTranscation->first_confirmed = 200;
            } else if ($shareRideTranscation->second_transaction == $data['transcation']) {
                $shareRideTranscation->second_confirmed = 200;
            }
        } else {
            if ($shareRideTranscation->first_transaction == $data['transcation']) {
                $shareRideTranscation->first_confirmed = 400;
            } else if ($shareRideTranscation->second_transaction == $data['transcation']) {
                $shareRideTranscation->second_confirmed = 400;
            }
        }
        $shareRideTranscation->save();

        //Both confirmed
        if ($shareRideTranscation->first_confirmed == 200 &&
            $shareRideTranscation->second_confirmed == 200) {
            $shareRideTranscation->status = 200;
            //Call the driver
            event(new ShareRideDriverEvent(
                new RideShareTransactionResource($shareRideTranscation),
                new DriverResource($driver),
                'shareRideSuccess'
            ));
        } else if ($shareRideTranscation->first_confirmed == 200 &&
            $shareRideTranscation->second_confirmed == 400) {

        } else if ($shareRideTranscation->first_confirmed == 400 &&
            $shareRideTranscation->second_confirmed == 200) {

                
        }
    }

    public function updateShareRideDriverStatus($message)
    {
        $data = json_decode($message, true);
        print_r("Share Ride Driver Response");
        print_r($data);
        $shareRideTranscation = RideShareTransaction::find($data['transcation']);
        $first_transcation = Transcation::find($shareRideTranscation->first_transaction);
        $second_transcation = Transcation::find($shareRideTranscation->second_transaction);
        $driver = Driver::find($data['driver']);
        if ($data['response'] == 1) { // Driver accept the call
            // Update the status of the two share ride transaction
            $first_transcation->status = 102;
            $first_transcation->driver_id = $driver->id;
            $first_transcation->taxi = $driver->taxi_id;
            $first_transcation->save();

            $second_transcation->status = 102;
            $second_transcation->driver_id = $driver->id;
            $second_transcation->taxi = $driver->taxi_id;
            $second_transcation->save();

            // Call the passenger
            $shareRideTranscation->status = 102;
            $shareRideTranscation->save();

            event(new ShareRideDriverEvent($rideShareTransactionResource, $driverResource, "passengerShareRideFound"));

            //Timeout event
            
        } else { // Driver reject the call
            $first_transcation->status = 100;
            $first_transcation->driver_id = $driver->id;
            $first_transcation->taxi = $driver->taxi_id;
            $first_transcation->save();

            $second_transcation->status = 100;
            $second_transcation->driver_id = $driver->id;
            $second_transcation->taxi = $driver->taxi_id;
            $second_transcation->save();

            $shareRideTranscation->status = 400;
            $shareRideTranscation->save();

            $driver->occupied = 0;
            $driver->transcation_id = 0;
            $driver->save();

            if ($first_transcation->cancelled != 0) {
                RideSharingProcessor::dispatch($first_transcation);
            }

            if ($second_transcation->cancelled != 0) {
                RideSharingProcessor::dispatch($second_transcation);
            }
        }

    }

    public function updateDriverStatus($message)
    {
        $data = json_decode($message, true);
        print_r("Driver Response");
        print_r($data);
        $transcation = Transcation::find($data['transcation']);
        $driver = Driver::find($data['driver']);

        //If the driver accept the order
        if ($data['response'] == 1) {
            // update the status of the driver
            $transcation->status = 102;
            $transcation->driver_id = $driver->id;
            $transcation->taxi = $driver->taxi_id;
            $transcation->save();
            // Push notification to the passenger (on driver found)
            event(new PassengerNotificationEvent($driver, $transcation, "passengerDriverFound"));
            PassengerTimeoutJob::dispatch($transcation, $driver)->delay(now()->addMinutes(5));
        } else {
            // restore the driver availability
            $driver->occupied = 0;
            $driver->transcation_id = 0;
            $driver->save();

            //If the transaction is not cancelled
            if ($transcation->status != 400) {
                $transcation->status = 100;
                $transcation->save();
                //Search another driver
                TransactionProcessor::dispatch($transcation);
            }
        }
    }

    public function updatePassengerStatus($message)
    {
        $data = json_decode($message, true);
        print_r("Passenger Response");
        print_r($data);
        $transcation = Transcation::find($data['transcation']);
        if ($data['response'] == 1) {
            // update the status of the driver and transaction
            if ($transcation->status != 400) {
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
            TransactionProcessor::dispatch($transcation);
        }
    }
}
