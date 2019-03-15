<?php

namespace App\Jobs;

use App\Driver;
use App\Transcation;
use App\RideShareTransaction;

use App\Events\ShareRideDriverEvent;
use App\Http\Resources\DriverResource;
use App\Http\Resources\RideShareTransactionResource;
use App\Jobs\RideShareDriverTimeoutJob;
use App\Jobs\RideSharingProcessor;

use App\Services\FindTaxiDriverInterface;
use App\Services\NoDriverFoundException;
use App\Services\TransactionCancelException;

use App\Utility\LocationUtlis;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7;

use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RideSharingProcessor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $transaction;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Transcation $transaction)
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
        // Eligible Transcation
        // 1. Share Ride request 2.Status: 100 3. Not cancelled 4. Not itself 
        $share_ride_transactions = Transcation::where([
            ['type', '=', 'r'],
            ['status', '=', '100'],
            ['id', '!=', $this->transaction->id],
            ['cancelled', '!=', '1']])->get();
        echo sizeof($share_ride_transactions);
        $googleAPIClient = new Client(['base_uri' => 'https://maps.googleapis.com/maps/api/',
            'timeout' => 60]);
        //Create some random transaction
        // $this->createRandomTransaction($googleAPIClient);
        $query = 'origin=' . $this->transaction->start_lat . ',' . $this->transaction->start_long . '&destination=' .
        $this->transaction->des_lat . ',' . $this->transaction->des_long . '&key=AIzaSyBwJyQDS_1ZZfic_OLFdB0q7UZC11B9vw4';
        try {
            $response = $googleAPIClient->get('directions/json',
                ['query' => $query]);
            if ($response->getStatusCode() == 200 && $response != null) {
                $content = json_decode($response->getBody()->getContents());
                if ($content->status == "OK") {
                    //select the first five points from the pick-up points
                    $pick_up_point = array();
                    $destination_point = array();
                    $steps = sizeof($content->routes[0]->legs[0]->steps);
                    $required_step = 2;
                    if ($steps > 10 && $steps < 20) {
                        $required_step = 3;
                    }

                    if ($steps > 20) {
                        $required_step = 4;
                    }
                    echo "steps: " . $required_step;

                    for ($i = 0; $i < $required_step; ++$i) {
                        $pick_up_point[$i]['lat'] = $content->routes[0]->legs[0]->steps[$i]->start_location->lat;
                        $pick_up_point[$i]['lng'] = $content->routes[0]->legs[0]->steps[$i]->start_location->lng;
                    }
                    for ($i = 0; $i < $required_step; ++$i) {
                        $destination_point[$i]['lat'] = $content->routes[0]->legs[0]->steps[$steps - 1 - $i]->start_location->lat;
                        $destination_point[$i]['lng'] = $content->routes[0]->legs[0]->steps[$steps - 1 - $i]->start_location->lng;
                    }
                    $potential_transactions = array();
                    foreach ($share_ride_transactions as $item) {
                        $eligible_pickUp = false;
                        $eligible_destination = false;
                        for ($i = 0; $i < $required_step; ++$i) {
                            if (LocationUtlis::getDistance($item->start_lat, $item->start_long,
                                $pick_up_point[$i]['lat'], $pick_up_point[$i]['lng']) < 1) {
                                $eligible_pickUp = true;
                            }
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
                        $paired_transaction = Transcation::find($potential_transactions[0]);
                        //Create a Ride Transaction Record
                        $rideTransaction = new RideShareTransaction;
                        $rideTransaction->status = 101;
                        $rideTransaction->first_transaction = $this->transaction->id;
                        $rideTransaction->second_transaction = $paired_transaction->id;
                        $rideTransaction->first_confirmed = 100;
                        $rideTransaction->second_confirmed = 100;
                        //find the driver and passenger
                        $findTaxiHelper = app(FindTaxiDriverInterface::class);
                        try {
                            // Find the available driver
                            $driverID = $findTaxiHelper->findTaxiDriver($this->transaction->id);
                            Log::info("Selected driver id:  " . $driverID);
                            $selectedDriver = Driver::find($driverID);

                            // update the status in the ride transaction table
                            $rideTransaction->driver_id = $driverID;
                            $rideTransaction->status = 101;
                            $rideTransaction->taxi_id = $selectedDriver->taxi_id;
                            $rideTransaction->save();

                            // update the status of the selected driver
                            $selectedDriver->occupied = 1;
                            $selectedDriver->transcation_id = $rideTransaction->id;
                            $selectedDriver->transcation_type = 's';
                            $selectedDriver->save();

                            // update the status of the two transactions
                            $this->transaction->status = 101;
                            $this->transaction->save();
                            $paired_transaction->status = 101;
                            $paired_transaction->save();

                            // Find the driver
                            $rideShareTransactionResource = new RideShareTransactionResource($rideTransaction);
                            $driverResource = new DriverResource($selectedDriver);
                            print_r($rideShareTransactionResource);
                            event(new ShareRideDriverEvent($rideShareTransactionResource, $driverResource, "shareRideDriverFound"));

                            //Driver timeout
                            RideShareDriverTimeoutJob::dispatch($selectedDriver, $rideTransaction)->delay(now()->addMinute(5));
                        } catch (NoDriverFoundException $e) { // Fail to find available driver

                        } catch (TransactionCancelException $e1) { // Transaction Cancelled

                        }
                    } else {
                        //dispatch again
                        RideSharingProcessor::dispatch($this->transaction)->delay(now()->addMinutes(3));
                    }
                } else {
                    echo "Route not found";
                }
            } else { //Empty body
                print_r("Request not found");
            }
        } catch (ConnectException $e) { //Timeout error
            echo "connection error";
        } catch (ClientException $e1) { // 404 Error
            echo Psr7\str($e1->getRequest());
            echo Psr7\str($e1->getResponse());
        }
    }

    public function createRandomTransaction($googleAPIClient)
    {
        try {
            $ride_position = array();
            $response = $googleAPIClient->get('place/nearbysearch/json',
                ['query' => 'location=22.314755558929125,114.16477605700494&radius=1500&key=AIzaSyBwJyQDS_1ZZfic_OLFdB0q7UZC11B9vw4']);
            if ($response->getStatusCode() == 200 && $response != null) {
                $content = json_decode($response->getBody()->getContents());
                if ($content->status == "OK") {
                    for ($i = 0; $i < 20; ++$i) {
                        $ride_position[$i]['start_lat'] = $content->results[$i]->geometry->location->lat;
                        $ride_position[$i]['start_lng'] = $content->results[$i]->geometry->location->lng;
                        $ride_position[$i]['start_addr'] = $content->results[$i]->name;
                    }
                } else {
                    echo "Route not found";
                }
            } else { //Empty body
                print_r("Request not found");
            }

            $response = $googleAPIClient->get('place/nearbysearch/json',
                ['query' => 'location=22.306249, 114.189161&radius=1500&key=AIzaSyBwJyQDS_1ZZfic_OLFdB0q7UZC11B9vw4']);
            if ($response->getStatusCode() == 200 && $response != null) {
                $content = json_decode($response->getBody()->getContents());
                if ($content->status == "OK") {
                    for ($i = 0; $i < 20; ++$i) {
                        $ride_position[$i]['des_lat'] = $content->results[$i]->geometry->location->lat;
                        $ride_position[$i]['des_lng'] = $content->results[$i]->geometry->location->lng;
                        $ride_position[$i]['des_addr'] = $content->results[$i]->name;
                    }
                } else {
                    echo "Route not found";
                }
            } else { //Empty body
                print_r("Request not found");
            }
            print_r($ride_position);
            for ($i = 0; $i < 20; ++$i) {
                $transcation = new Transcation;
                $transcation->user_id = 14;
                $transcation->start_lat = $ride_position[$i]['start_lat'];
                $transcation->start_long = $ride_position[$i]['start_lng'];
                $transcation->start_addr = $ride_position[$i]['start_addr'];
                $transcation->des_lat = $ride_position[$i]['des_lat'];
                $transcation->des_long = $ride_position[$i]['des_lng'];
                $transcation->des_addr = $ride_position[$i]['des_addr'];
                $transcation->status = 100;
                $transcation->type = 'r';
                $transcation->meet_up_time = null;
                $transcation->save();
            }
        } catch (ConnectException $e) { //Timeout error
            echo "connection error";
        } catch (ClientException $e1) { // 404 Error
            echo Psr7\str($e1->getRequest());
            echo Psr7\str($e1->getResponse());
        }
    }
}
