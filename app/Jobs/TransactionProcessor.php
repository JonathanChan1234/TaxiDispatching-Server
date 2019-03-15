<?php

namespace App\Jobs;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Redis;

use App\Jobs\ResumeDriverStatus;
use App\Events\DriverFoundEvent;
use App\Transcation;
use App\Driver;
use \Datetime;
use \DateTimeZone;
/**
 * Transaction Processor Job which will search for available drivers
 */
class TransactionProcessor implements ShouldQueue
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
        if($this->transcation->status != 400) {
            Log::Info("Handle transaction ". $this->transcation->id);
            //Retrieve all the available drivers (which they have logged in a taxi account)
            $drivers = Driver::where('occupied', 0)->get();
            // Iterate every available drivers
            $drivers->each(function ($item, $key) {
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
                    $distance = $this->getDistance($latitude, $longitude, $this->transcation->start_lat, $this->transcation->start_long);
                    echo 'Distance: '.$distance;
                    // eligible drivers (distance between driver and pick up point is less than 3 km)
                    if($distance < 5) {
                        // Update the status of the selected driver
                        $item->occupied = 1;
                        $item->transcation_id = $this->transcation->id;
                        $item->transcation_type = 'p';
                        $eligible_drivers = $item;
                        $item->save();
    
                        //Update the status of the transaction
                        $this->transcation->status = 101;
                        $jobId = $this->job->getJobId();
                        $this->transcation->save();
                        
                        Log::Info("Selected driver id:  ". $item->id);
                        
                        // start timer for the response (5 mins)
                        ResumeDriverStatus::dispatch($this->transcation, $item)->delay(now()->addMinutes(5));
                        event(new DriverFoundEvent($item, $this->transcation));
                        return false; //break the loop
                    }
                    //location is valid within 3 minutes
                    // if($interval->i == 3
                    // && $interval->h == 0
                    // && $interval->d == 0
                    // && $interval->m == 0
                    // && $interval->y == 0) {
                    //     //Calculate distance between two points
                    //     $distance = $this->getDistance($latitude, $longitude, $this->transcation->start_lat, $this->transcation->start_long);
                    //     echo 'Distance: '.$distance;
                    //     // eligible drivers (distance between driver and pick up point is less than 3 km)
                    //     if($distance < 5) {
                    //         // Update the status of the selected driver
                    //         $item->occupied = 1;
                    //         $item->transcation_id = $transcation->id;
                    //         $eligible_drivers = $item;
                    //         event(new DriverFoundEvent($item, $this->transcation));
                    //     }
                    // }
                }
            });
        }
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
