<?php

namespace App\Events;

use App\Driver;
use App\Transcation;
use App\Http\Resources\DriverResource;
use App\Http\Resources\TranscationResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * This event is called when the driver reached the pick-up point
 * The passenger has to reach within the 5 mins
 */
class PassengerDriverReachEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $driver, $transcation, $event, $time;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Driver $driverObject, Transcation $transcation, $time) 
    {
        $this->driver = new DriverResource($driverObject);
        $this->transcation = new TranscationResource($transcation);
        $this->time = $time;
        $this->event = "passengerDriverReach";
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('passengerNotification');
    }
}
