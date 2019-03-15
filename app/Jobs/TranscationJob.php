<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Jobs\ResumeDriverStatus;
use App\Events\DriverFoundEvent;
use App\Transcation;
use App\Driver;
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
         if($this->transcation->id != 400) {
            Log::Info("Handle transaction ". $this->transcation->id);
            $selected_driver = $this->findDriver(3);
            if($selected_driver == 0) {
                $selected_driver = $this->findDriver(5);
            }
            if($selected_driver == 0) {
                $selected_driver = $this->findDriver(0);
            }
            Log::Info("Selected Driver ". $selected_driver);
            $this->updateTranscationStatus($selected_driver);
        }
    }

    function findDriver($search_distance) {
        $selectedDrivers = array();
        $driver_distance = array();
        //Retrieve all the available drivers (which they have logged in a taxi account)
        $drivers = Driver::where('occupied', 0)->get();
        // Iterate every available drivers
        foreach($drivers as $driver) {
            if(Redis::hexists($driver->id, "latitude")) {
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

                // test without timestamp
                $distance = $this->getDistance($latitude, $longitude, $this->transcation->start_lat, $this->transcation->start_long);
                // eligible drivers (distance between driver and pick up point is less than 3 km)
                if($search_distance == 0) {
                    $selectedDrivers[] = $driver->id;
                    $driver_distance[] = $distance;
                }
                else if($distance < $search_distance) {
                    // Update the status of the selected driver 
                    $selectedDrivers[] = $driver->id;
                    $driver_distance[] = $distance;
                }
            }
        }
        
        if(sizeof($selectedDrivers) > 0) {
            array_multisort($driver_distance, $selectedDrivers);
            // $random_index = rand(0, (sizeof($selectedDrivers)-1));
            // print_r($random_index);
            print_r($driver_distance);
            return $selectedDrivers[0];
        } else {
            return 0;   //Not found
        }
    }

    function updateTranscationStatus($selected_driver) {
        $driver = Driver::find($selected_driver);
        $driver->occupied = 1;
        $driver->transcation_id = $this->transcation->id;
        $driver->save();

        //Update the status of the transaction
        $this->transcation->status = 101;
        $jobId = $this->job->getJobId();
        $this->transcation->save();
        
        // start timer for the response (5 mins)
        ResumeDriverStatus::dispatch($this->transcation, $driver)->delay(now()->addMinutes(5));
        event(new DriverFoundEvent($driver, $this->transcation));
    }

    function getDistance($latitude1, $longitude1, $latitude2, $longitude2) {  
        $earth_radius = 6371;  
          
        $dLat = deg2rad($latitude2 - $latitude1);  
        $dLon = deg2rad($longitude2 - $longitude1);  
          
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($dLon/2) * sin($dLon/2);  
        $c = 2 * asin(sqrt($a));  
        $d = $earth_radius * $c;  
          
        return $d;  
    }  
}
