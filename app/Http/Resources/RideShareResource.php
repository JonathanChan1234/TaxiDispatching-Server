<?php

namespace App\Http\Resources;
use \Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class RideShareResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)

    {
        return [
            'id' => $this->id,
            'user' => $this->user,
            'rideshare_id' =>$this->rideshare_id,
            'start_lat' => $this->start_lat,
            'start_long' => $this->start_long,
            'start_addr' => $this->start_addr,
            'des_lat' => $this->des_lat,
            'des_long' => $this->des_long,
            'des_addr' => $this->des_addr,
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->created_at->toDateTimeString(),
            'driverReachTime' => $this->driverReachTime
        ]; 
    }
}
