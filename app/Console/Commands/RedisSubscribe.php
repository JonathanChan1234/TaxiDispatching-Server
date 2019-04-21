<?php

namespace App\Console\Commands;

use App\Driver;
use App\Events\PassengerNotificationEvent;
use App\Jobs\PassengerTimeoutJob;
use App\Jobs\RideSharingProcessor;
use App\RideShare;
use App\RideShareTransaction;
use App\Services\RatingService\AddRatingInterface;
use App\Transcation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                default:
                    break;
            }
        });
    }

    public function updateShareRideDriverStatus($message)
    {
        $data = json_decode($message, true);
        print_r("Share Ride Driver Response");
        print_r($data);

        $driver_id = $data['driver'];
        $transaction_id = $data['transcation'];
        $response = $data['response'];
        Log::info("Share ride id " . $transaction_id);
        Log::info("Driver " . $driver_id . " timeout");

        DB::beginTransaction();
        try {
            // Lock the two record first
            $shareRideTranscation = RideShareTransaction::find($transaction_id);
            $first_transcation_query = RideShare::where('id', $shareRideTranscation->first_transaction);
            $first_transcation = $first_transcation_query->lockForUpdate()->first();
            $second_transcation_query = RideShare::where('id', $shareRideTranscation->second_transaction);
            $second_transcation = $second_transcation_query->lockForUpdate()->first();

            $driver = Driver::find($driver_id);

            //case 1: Both transactions are not cancelled
            if ($first_transcation->cancelled != 1 && $second_transcation->cancelled != 1) {
                $first_transcation->status = 100;
                $first_transcation->rideshare_id = 0;
                $first_transcation->save();

                $second_transcation->status = 100;
                $second_transcation->rideshare_id = 0;
                $second_transcation->save();

                $shareRideTranscation->status = 400;
                $shareRideTranscation->save();

                $driver->occupied = 0;
                $driver->transcation_id = 0;
                $driver->save();
                DB::commit();

                if($response != 1) {
                    RideSharingProcessor::dispatch($first_transcation);
                    RideSharingProcessor::dispatch($second_transcation);
                }
            }

            // Case 2: first transaction cancelled and second transaction did not (Fail)
            if ($first_transcation->cancelled == 1 && $second_transcation->cancelled != 1) {
                $second_transcation->status = 100;
                $second_transcation->rideshare_id = 0;
                $second_transcation->save();

                $shareRideTranscation->status = 400;
                $shareRideTranscation->save();

                $driver->occupied = 0;
                $driver->transcation_id = 0;
                $driver->save();

                DB::commit();
                RideSharingProcessor::dispatch($second_transcation);
            }

            // case 3: second transaction cancelled and first transaction did not (Fail)
            if ($first_transcation->cancelled != 1 && $second_transcation->cancelled == 1) {
                $first_transcation->status = 100;
                $first_transcation->rideshare_id = 0;
                $first_transcation->save();

                $shareRideTranscation->status = 400;
                $shareRideTranscation->save();

                $driver->occupied = 0;
                $driver->transcation_id = 0;
                $driver->save();
                DB::commit();

                RideSharingProcessor::dispatch($first_transcation);
            }
            // case 4: both cancelled  (Fail)
            if ($first_transcation->cancelled == 1 && $second_transcation->cancelled == 1) {
                $shareRideTranscation->status = 400;
                $shareRideTranscation->save();

                $driver->occupied = 0;
                $driver->transcation_id = 0;
                $driver->save();
                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::info($e);
        }
    }

    public function updateDriverStatus($message)
    {
        $data = json_decode($message, true);
        print_r("Driver Response");
        print_r($data);
        $transcation = Transcation::find($data['transcation']);
        $driver = Driver::find($data['driver']);
        $response = $data['response'];

        //If the driver accept the order
        if ($data['response'] == 1 &&
            $transcation->driver_id == $driver->id &&
            $transcation->status == 101) {
            // update the status of the driver
            $transcation->status = 102;
            $transcation->driver_id = $driver->id;
            $transcation->taxi = $driver->taxi_id;
            $transcation->save();

            date_default_timezone_set('Asia/Hong_Kong');
            $t = time();
            $time = date("Y-m-d H:i:s", $t);
            // Push notification to the passenger (on driver found)
            event(new PassengerNotificationEvent($driver, $transcation, $time, "passengerDriverFound"));
            PassengerTimeoutJob::dispatch($transcation, $driver)->delay(now()->addMinutes(3));
        } else if ($data['response'] == 0 &&
            $transcation->driver_id == $driver->id &&
            $transcation->status == 101) {

            // restore the driver availability
            $driver->occupied = 0;
            $driver->transcation_id = 0;
            $driver->save();

            $addRatingHelper = app(AddRatingInterface::class);
            $new_rating = $addRatingHelper->addRating(
                $driver->id,
                0,
                $addRatingHelper::REJECT_RIDE,
                'Passenger Rating in personal ride ' . $transcation->id);

            $transcation->status = 100;
            $transcation->save();
            //Search another driver
            TransactionJob::dispatch($transcation);
        } else {
            if ($transcation->driver_id != $driver->id) {
                //Timeout
            }

            if ($transcation->status == 400) {
                //Transaction Cancelled
            }
        }

        //Error?
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
            $driver->transcation_id = 0;
            $driver->save();

            $transcation->status = 400;
            $transcation->cancelled = 1;
            $transcation->save();
        }
    }
}
