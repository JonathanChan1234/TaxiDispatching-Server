<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Utils\Utils;
use App\Transcation;
use App\Driver;

class TranscationProcessing implements ShouldQueue
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
        Log::info("Job started");
        // $eligible_drivers = array();
        // //Retrieve all the available drivers
        // $drivers = Driver::where('occupied', 0)->get();
        //Iterate every available drivers
        // $drivers->each(function ($item, $key) {
        //     print_r($item->id);
        //     if(Redis::hexists($item->id, "latitude")) {
        //         //Retrieve the location data and timestamp from Redis
        //         $location = Redis::hget($item->id);
        //         print_r($item->id);
        //         print_r($location);
        //         $latitude = $location->latitude;
        //         $longitude = $location->longitude;
        //         $locationTime = $location->timestamp;
        //         //compare timestamp (Check whether the location is "outdated")
        //         $currentTime = new DateTime('now');
        //         $locationTime = new DateTime($timestamp);
        //         $interval = $currentTime->diff($locationTime);
        //         if($interval->s < 180) {
        //             //Calculate distance between two points
        //             $distance = Utils::findDistance($latitude, $longitude, $transcation->start_lat, $transcation->start_long);
        //             // eligible drivers (distance between driver and pick up point is less than)
        //             // if($distance < 3) {
        //             //     $eligible_drivers[] = $item;
        //             // }
        //         }
        //     }
        // });
        
    }
}
