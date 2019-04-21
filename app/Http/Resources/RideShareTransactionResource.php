<?php

namespace App\Http\Resources;

use App\Http\Resources\RideShareResource;
use Illuminate\Http\Resources\Json\JsonResource;

class RideShareTransactionResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'first_transaction' => new RideShareResource($this->transaction_one),
            'second_transaction' => new RideShareResource($this->transaction_two),
            'driver' => $this->driver,
            'taxi' => $this->taxi,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'first_confirmed' => $this->first_confirmed,
            'second_confirmed' => $this->second_confirmed,
            'firstReachTime' => $this->firstReachTime,
            'secondReachTime' => $this->secondReachTime,
        ];
    }
}
