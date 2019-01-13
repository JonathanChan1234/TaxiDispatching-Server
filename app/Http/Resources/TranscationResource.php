<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TranscationResource extends JsonResource
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
            'driver' => $this->driver,
            'start_lat' => $this->start_lat,
            'start_long' => $this->start_long,
            'start_addr' => $this->start_addr,
            'des_lat' => $this->des_lat,
            'des_long' => $this->des_long,
            'des_addr' => $this->des_addr,
            'requirement' => $this->requirement,
            'status' => $this->status,
            'meet_up_time' => $this->meet_up_time,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
