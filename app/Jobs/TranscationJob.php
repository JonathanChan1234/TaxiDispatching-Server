<?php

namespace App\Jobs;

use App\Driver;
use App\Transcation;

use App\Events\DriverFoundEvent;
use App\Jobs\ResumeDriverStatus;
use App\Jobs\TranscationJob;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

use \Datetime;
use \DateTimeZone;

class TranscationJob implements ShouldQueue
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
        $this->transcation = $transcation;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //If the status is 400 (cancel)
        if ($this->transcation->id != 400) {
            Log::Info("Handle transaction " . $this->transcation->id);
            $selected_driver = $this->findDriver(3);
            if ($selected_driver == 0) {
                $selected_driver = $this->findDriver(5);
            }
            if ($selected_driver == 0) {
                $selected_driver = $this->findDriver(0);
            }
            if ($selected_driver == 0) {
                TranscationJob::dispatch($this->transcation)->delay(now()->addMinutes(3));
            }
            Log::Info("Selected Driver " . $selected_driver);
            $this->updateTranscationStatus($selected_driver);
        }
    }

    public function findDriver($search_distance)
    {
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
                    $distance = $this->getDistance($latitude, $longitude, $this->transcation->start_lat, $this->transcation->start_long);
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
            print_r($driver_distance);
            print_r($driver_rating);
            print_r($selectedDrivers);
            $json = array();
            for ($i = 0; $i < sizeof($selectedDrivers); ++$i) {
                $json[$i]['id'] = $selectedDrivers[$i];
                $json[$i]['distance'] = $driver_distance[$i];
                $json[$i]['rating'] = $driver_rating[$i];
            }
            $message['event'] = "driverComparison";
            $message['driver'] = $json;
            $message['transcation'] = $this->transcation->id;
            $data['data'] = $message;
            Redis::publish('admin', json_encode($data));
            return $selectedDrivers[0];
        } else {
            return 0; //Not found
        }
    }

    public function getDriverRating($driver_rating)
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

    public function updateTranscationStatus($selected_driver)
    {
        DB::beginTransaction();
        $driverQuery = Driver::where('id', $selected_driver);
        $driver = $driverQuery->lockForUpdate()->first();

        $driver->occupied = 1;
        $driver->transcation_id = $this->transcation->id;
        $driver->transcation_type = 'p';
        $driver->save();

        //Update the status of the transaction
        $transcationQuery = Transcation::where('id', $this->transcation->id);
        $transcation = $transcationQuery->lockForUpdate()->first();
        $transcation->status = 101;
        $transcation->driver_id = $driver->id;

        $jobId = $this->job->getJobId();
        $transcation->save();

        DB::commit();
        
        date_default_timezone_set('Asia/Hong_Kong');
        $t = time();
        $time = date("Y-m-d H:i:s", $t);

        // start timer for the response (5 mins)
        ResumeDriverStatus::dispatch($this->transcation, $driver)->delay(now()->addMinutes(2));
        event(new DriverFoundEvent($driver, $this->transcation, $time));
    }

    public function getDistance($latitude1, $longitude1, $latitude2, $longitude2)
    {
        $earth_radius = 6371;

        $dLat = deg2rad($latitude2 - $latitude1);
        $dLon = deg2rad($longitude2 - $longitude1);

        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * asin(sqrt($a));
        $d = $earth_radius * $c;

        return $d;
    }
}
