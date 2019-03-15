<?php

namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

use App\Jobs\ResumeDriverStatus;
use App\Events\DriverFoundEvent;
use App\Utility\LocationUtlis;
use App\Transcation;
use App\Driver;
use \Datetime;
use \DateTimeZone;
/**
 * Find the nearest driver according to their positions
 */
class FindTaxiDriverService implements FindTaxiDriverInterface
{
    
    public function findTaxiDriver($transcationId)
    {   
        $transcation = Transcation::find($transcationId);
        //If the status is 400 (cancel)
        if($transcation->status != 400) {
            Log::Info("Handle transaction ". $transcation->id);
            //Retrieve all the available drivers (which they have logged in a taxi account)
            $drivers = Driver::where('occupied', 0)->get();
            // Iterate every available drivers
            foreach($drivers as $item) {
                if(Redis::hexists($item->id, "latitude")) {
                    // Retrieve the location data and timestamp from Redis
                    $location = Redis::hgetall($item->id);
                    $latitude = Redis::hget($item->id, "latitude");
                    $longitude = Redis::hget($item->id, "longitude");
                    $locationTime = Redis::hget($item->id, "timestamp");
                    
                    // compare timestamp (Check whether the location is "outdated")
                    $currentTime = new DateTime('now');
                    $currentTime->setTimezone(new DateTimeZone('Asia/Hong_Kong'));
                    $locationTime = new DateTime($locationTime);
                    $interval = $currentTime->diff($locationTime);
    
                    // test without timestamp
                    $distance = LocationUtlis::getDistance($latitude, $longitude, $transcation->start_lat, $transcation->start_long);
                    echo 'Distance: '.$distance;
                    // eligible drivers (distance between driver and pick up point is less than 3 km)
                    if($distance < 5) {
                        // Update the status of the selected driver
                        $item->occupied = 1;
                        $item->transcation_id = $transcation->id;
                        $eligible_drivers = $item;
                        $item->save();
    
                        //Update the status of the transaction
                        $transcation->status = 101;
                        $transcation->save();
                        
                        Log::Info("Selected driver id:  ". $item->id);
                        // Return the selected driver id
                        return $item->id; //break the loop
                    }
                }
            }
            throw new NoDriverFoundException();
        } else {
            throw new TransactionCancelException();
        }
    }
}
