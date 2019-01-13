<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return[
            'id' => $this->id,
            'username' => $this->username,
            'phonenumber' => $this->phonenumber,
            'lat' => $this->lat,
            'long' => $this->longitude,
            'occupied' => $this->occupied,
            'location_updated' => (string) $this->location_updated,
            'updated_at' =>(string) $this->created_at,
        ];
    }
}
