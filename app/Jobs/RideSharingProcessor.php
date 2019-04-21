<?php

namespace App\Jobs;

use App\Driver;
use App\RideShare;
use App\RideShareTransaction;

use App\Events\ShareRideDriverEvent;

use App\Http\Resources\DriverResource;
use App\Http\Resources\RideShareTransactionResource;

use App\Jobs\RideSharingProcessor;

use App\Services\FindTaxiDriver\FindTaxiDriverInterface;
use App\Services\FindTaxiDriver\NoDriverFoundException;

use App\Utility\LocationUtlis;

use Carbon\Carbon;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RideSharingProcessor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $transaction;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(RideShare $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->transaction->status == 100) {
            $googleAPIClient = new Client(['base_uri' => 'https://maps.googleapis.com/maps/api/',
                'timeout' => 60]);
            $query = 'origin=' . $this->transaction->start_lat . ',' . $this->transaction->start_long . '&destination=' .
            $this->transaction->des_lat . ',' . $this->transaction->des_long .
                '&alternatives=true&key=AIzaSyBwJyQDS_1ZZfic_OLFdB0q7UZC11B9vw4';
            try {
                $response = $googleAPIClient->get('directions/json',
                    ['query' => $query]);
                if ($response->getStatusCode() == 200 && $response != null) {
                    $content = json_decode($response->getBody()->getContents());
                    if ($content->status == "OK") {
                        $potentials = $this->findPotentialTransaction($content->routes);
                        if (sizeof($potentials) > 0) {
                            $findTaxiHelper = app(FindTaxiDriverInterface::class);
                            try {
                                $driver = $findTaxiHelper->findTaxiDriver($this->transaction->id, "s");
                                Log::info("Share Ride Processing-> Selected driver id:  " . $driver);
                                $this->transaction->delete();
                                // $this->updateTransactionStatus($potentials[0]);
                            } catch (NoDriverFoundException $e) {
                                Log::info("Share Ride Processing-> No driver found");
                                RideSharingProcessor::dispatch($this->transaction)
                                    ->delay(now()->addMinute(2));
                            }

                        } else {
                            RideSharingProcessor::dispatch()->delay(now()->addMinutes(3));
                        }
                    } else { // Cancelled sent to the passenger
                        echo "Route not found";
                    }
                }
            } catch (ConnectException $e) { //Timeout error
                echo "connection error";
            } catch (ClientException $e1) { // 404 Error
                echo Psr7\str($e1->getRequest());
                echo Psr7\str($e1->getResponse());
            }
        }
    }

    private function findPotentialTransaction($routes)
    {
        // Eligible Transcation
        // 1. Share Ride request 2.Status: 100 3. Not cancelled 4. Not itself
        $share_ride_transactions = RideShare::where([
            ['status', '=', '100'],
            ['id', '!=', $this->transaction->id],
            ['cancelled', '!=', '1']])
            ->orderBy('created_at', 'desc')
            ->get();

        $potentials = array();
        foreach ($routes as $route) {
            //select the first five points from the pick-up points
            $pick_up_point = array();
            $destination_point = array();
            $steps = sizeof($route->legs[0]->steps);
            $check_distance = 0;

            //Check the distance of the route
            if ($route->legs[0]->distance->value > 3000) {
                $check_distance = 1000;
            } else if ($route->legs[0]->distance->value < 3000
                && $route->legs[0]->distance->value > 1000) {
                $check_distance = 500;
            } else {
                $check_distance = $route->legs[0]->distance->value;
            }

            $distance = 0;
            for ($i = 0; $i < $steps; ++$i) {
                $distance += $route->legs[0]->steps[$i]->distance->value;
                print_r('Distance ' . $distance);
                if ($distance > $check_distance) {
                    $required_start_step = $i - 1;
                    break;
                }
            }

            $distance = 0;
            for ($i = (sizeof($route->legs[0]->steps) - 1); $i > 0; --$i) {
                $distance += $route->legs[0]->steps[$i]->distance->value;
                print_r('Distance ' . $distance);
                if ($distance > $check_distance) {
                    $required_end_step = $steps - $i - 1;
                    break;
                }
            }

            echo "start steps: " . $required_start_step;
            echo "end steps: " . $required_end_step;

            for ($i = 0; $i < $required_start_step; ++$i) {
                $pick_up_point[$i]['lat'] = $route->legs[0]->steps[$i]->start_location->lat;
                $pick_up_point[$i]['lng'] = $route->legs[0]->steps[$i]->start_location->lng;
            }
            for ($i = 0; $i < $required_end_step; ++$i) {
                $destination_point[$i]['lat'] = $route->legs[0]->steps[$steps - 1 - $i]->end_location->lat;
                $destination_point[$i]['lng'] = $route->legs[0]->steps[$steps - 1 - $i]->end_location->lng;
            }
            $potential_transactions = array();
            foreach ($share_ride_transactions as $item) {
                $eligible_pickUp = false;
                $eligible_destination = false;
                //Check pick-up position
                for ($i = 0; $i < $required_start_step; ++$i) {
                    if (LocationUtlis::getDistance($item->start_lat, $item->start_long,
                        $pick_up_point[$i]['lat'], $pick_up_point[$i]['lng']) < 1) {
                        $eligible_pickUp = true;
                    }
                }

                //Check destination position
                for ($i = 0; $i < $required_end_step; ++$i) {
                    if (LocationUtlis::getDistance($item->des_lat, $item->des_long,
                        $destination_point[$i]['lat'], $destination_point[$i]['lng']) < 1) {
                        $eligible_destination = true;
                    }
                }

                if ($eligible_destination && $eligible_pickUp) {
                    $potential_transactions[] = $item->id;
                }
            }

            print_r($potential_transactions);
            if (sizeof($potential_transactions) > 0) {
                $temp = $potential_transactions;
            } else {
                $temp = null;
            }

            if ($temp != null) {
                $potentials = array_merge($temp, $potentials);
            }
        }
        return array_unique($potentials);
    }

    private function updateTransactionStatus($potential_transactions, $driver)
    {
        Log::info("Handling share ride " . $this->transaction->id);
        try {
            // Lock the table
            DB::beginTransaction();

            // Lock the paired transaction
            $paired_transaction_query = RideShare::where('id', $potential_transactions);
            $paired_transaction = $paired_transaction_query->lockForUpdate()->first();

            // Lock the processing transaction
            $transaction_query = RideShare::where('id', $this->transaction->id);
            $transaction = $transaction_query->lockForUpdate()->first();

            // Lock the driver
            $driver_query = Driver::find($driver);
            $selectedDriver = $driver_query->lockForUpdate()->first();

            // Both transaction are not cancelled
            if ($transaction->status != 400 && $paired_transaction->status != 400) {

                // Create a new entry in the share ride transaction
                $rideTransaction = new RideShareTransaction;
                $rideTransaction->status = 101;
                $rideTransaction->first_transaction = $transaction->id;
                $rideTransaction->second_transaction = $paired_transaction->id;
                $rideTransaction->first_confirmed = 100;
                $rideTransaction->second_confirmed = 100;
                $rideTransaction->driver_id = $driver;
                $rideTransaction->status = 101;
                $rideTransaction->taxi_id = $selectedDriver->taxi_id;
                $rideTransaction->save();

                // update the status
                $transaction->status = 101;
                $transaction->rideshare_id = $rideTransaction->id;
                $transaction->save();

                // update the status
                $paired_transaction->status = 101;
                $paired_transaction->rideshare_id = $rideTransaction->id;
                $paired_transaction->save();

                // update the status of the selected driver
                $selectedDriver->occupied = 1;
                $selectedDriver->transcation_id = $rideTransaction->id;
                $selectedDriver->transcation_type = 's';
                $selectedDriver->save();

                DB::commit();

                // Find the driver
                $rideShareTransactionResource = new RideShareTransactionResource($rideTransaction);
                $driverResource = new DriverResource($selectedDriver);

                date_default_timezone_set('Asia/Hong_Kong');
                $t = time();
                $time = date("Y-m-d H:i:s", $t);

                // print_r($rideShareTransactionResource);
                event(new ShareRideDriverEvent($rideShareTransactionResource,
                    $driverResource,
                    $time,
                    "shareRideDriverFound"));

                //Driver timeout
                RideShareDriverTimeoutJob::dispatch($selectedDriver, $rideTransaction)
                    ->delay(now()->addMinute(3));

            } else if ($transaction->status != 400) { //the first transaction is cancelled
                DB::rollback();
                RideSharingProcessor::dispatch($transaction)
                    ->delay(Carbon::now()->addSeconds(5));
            } else if ($paired_transaction->status != 400) { //the second transaction is cancelled
                DB::rollback();
                RideSharingProcessor::dispatch($paired_transaction)
                    ->delay(Carbon::now()->addSeconds(5));
            } else { // Both transactions are cancelled
                Log::info("Handling share ride cancelled");
                DB::rollback();
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::info("Handling share ride racing condition");
            RideSharingProcessor::dispatch($this->transaction)
                ->delay(Carbon::now()->addSeconds(5));
        }
    }
}
