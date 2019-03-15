<?php

namespace App\Events;

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

class DriverNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    protected $driver;
    protected $transaction;
    public $driverResource;
    public $transactionResource;
    public $event;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($driver, $transaction, $event)
    {
        $this->driver = $driver;
        $this->driverResource = new DriverResource($driver);
        $this->transaction = $transaction;
        $this->transactionResource = new TranscationResource($transaction);
        $this->event = $event;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('driverNotification');
    }
}
