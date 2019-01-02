<?php

namespace App\Events;

use Illuminate\Support\Facades\Log;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use App\Http\Resources\TranscationResource;
use App\Http\Resources\DriverResource;
use App\Driver;
use App\Transcation;

class DriverFoundEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    protected $driver;
    protected $transcation;
    public $driverResource;
    public $transcationResource;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Driver $driver, Transcation $transcation)
    {
        $this->driver = $driver;
        $this->transcation = $transcation;
        $this->driverResource = new DriverResource($driver);
        $this->transcationResource = new TranscationResource($transcation);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('driverFound');
    }
}
