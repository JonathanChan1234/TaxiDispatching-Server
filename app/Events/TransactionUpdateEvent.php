<?php

namespace App\Events;
use App\Transcation;
use App\Http\Resources\TranscationResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TransactionUpdateEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $transcation, $event;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Transcation $transcation)
    {
        $this->transcation = new TranscationResource($transcation);
        $this->event = "transactionUpdate";
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('admin');
    }
}
