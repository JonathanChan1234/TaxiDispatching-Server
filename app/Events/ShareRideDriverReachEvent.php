<?php

namespace App\Events;

use App\RideShareTransaction;
use App\Http\Resources\RideShareTransactionResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ShareRideDriverReachEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $transcation, $time, $event, $passengerId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(RideShareTransaction $transcation, $passengerId, $time)
    {
        $this->transcation = new RideShareTransactionResource($transcation);
        $this->time = $time;
        $this->passengerId = $passengerId;
        $this->event = "shareRideDriverReach";
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel("passengerNotification");
    }
}
