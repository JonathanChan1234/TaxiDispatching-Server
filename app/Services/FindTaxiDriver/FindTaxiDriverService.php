<?php

namespace App\Services\FindTaxiDriver;

use App\Driver;
use App\RideShare;
use App\Transcation;

use App\Utility\LocationUtlis;

use \Datetime;
use \DateTimeZone;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

/**
 * Find the nearest driver according to their positions
 */
class FindTaxiDriverService implements FindTaxiDriverInterface
{
    /**
     * type: p (personal)/ s(share ride)
     * transactionId: id
     */
    public function findTaxiDriver($transcationId, $type)
    {
        $search_distance = 3;
        //Retrieve all the available drivers (which they have logged in a taxi account)
        if ($type == "s") {
            $transcation = RideShare::find($transcationId);
            $drivers = Driver::where('occupied', '=', 0)
                ->where(function ($query) {
                    $query->where('ride_available', '=', 's')
                        ->orWhere('ride_available', '=', 'b');
                })
                ->where('taxi_id', '!=', 0)
                ->get();
        } else {
            $transcation = Transcation::find($transcationId);
            $drivers = Driver::where('occupied', '=', 0)
                ->where(function ($query) {
                    $query->where('ride_available', '=', 'p')
                        ->orWhere('ride_available', '=', 'b');
                })
                ->where('taxi_id', '!=', 0)
                ->get();
        }
        
        $selectedDrivers = array();
        $driver_distance = array();
        $driver_rating = array();

        //Retrieve all the available drivers (which they have logged in a taxi account)
        $drivers = Driver::where('occupied', 0)
            ->where(function ($query) {
                $query->where('ride_available', '=', 'p')
                    ->orWhere('ride_available', '=', 'b');
            })
            ->where('taxi_id', '!=', 0)
            ->get();
        
        $rating = DB::table('drivers')
            ->leftJoin('ratings', 'ratings.driver_id', '=', 'drivers.id')
            ->groupBy('drivers.id')
            ->orderBy('drivers.id', 'desc')
            ->select('drivers.id', DB::raw('AVG(ratings.rating) as rating'))
            ->get();
        
        $allRatings = $this->getDriverRating($rating);

        // Iterate every available drivers
        foreach ($drivers as $driver) {
            if (Redis::hexists($driver->id, "latitude")) {
                // Retrieve the location data and timestamp from Redis
                $location = Redis::hgetall($driver->id);
                $latitude = Redis::hget($driver->id, "latitude");
                $longitude = Redis::hget($driver->id, "longitude");
                $locationTime = Redis::hget($driver->id, "timestamp");

                // compare timestamp (Check whether the location is "outdated")
                $currentTime = new DateTime('now');
                $currentTime->setTimezone(new DateTimeZone('Asia/Hong_Kong'));
                $locationTime = new DateTime($locationTime);
                $interval = $currentTime->diff($locationTime);

                // test with timestamp
                if ($interval->y == 0 &&
                    $interval->m == 0 &&
                    $interval->d == 0 &&
                    $interval->h == 0 &&
                    $interval->i < 3) {
                    $distance = LocationUtlis::getDistance($latitude, $longitude, $transcation->start_lat, $transcation->start_long);
                    // eligible drivers (distance between driver and pick up point is less than 3 km)
                    if ($search_distance == 0) {
                        $selectedDrivers[] = $driver->id;
                        $driver_distance[] = floor($distance * 2) / 2;
                        $driver_rating[] = $allRatings[$driver->id]['rating'];
                    } else if ($distance < $search_distance) {
                        // Update the status of the selected driver
                        $selectedDrivers[] = $driver->id;
                        $driver_distance[] = floor($distance * 2) / 2;
                        $driver_rating[] = $allRatings[$driver->id]['rating'];
                    }
                }
            }
        }

        if (sizeof($selectedDrivers) > 0) {
            array_multisort($driver_distance, SORT_ASC, SORT_NUMERIC,
                $driver_rating, SORT_DESC, SORT_NUMERIC,
                $selectedDrivers);
            // print_r($driver_distance);
            // print_r($driver_rating);
            // print_r($selectedDrivers);
            $json = array();
            for ($i = 0; $i < sizeof($selectedDrivers); ++$i) {
                $json[$i]['id'] = $selectedDrivers[$i];
                $json[$i]['distance'] = $driver_distance[$i];
                $json[$i]['rating'] = $driver_rating[$i];
            }
            $message['event'] = "driverComparison";
            $message['driver'] = $json;
            $message['transcation'] = $transcation->id;
            $data['data'] = $message;
            Redis::publish('admin', json_encode($data));
            return $selectedDrivers[0];
        } else {
            throw new NoDriverFoundException;
        }
    }

    private function getDriverRating($driver_rating)
    {
        $ratings = array();
        foreach ($driver_rating as $rating) {
            if ($rating->rating == null) {
                $ratings[$rating->id]['rating'] = 3;
            } else {
                $ratings[$rating->id]['rating'] = $rating->rating;
            }
        }
        return $ratings;
    }
}
