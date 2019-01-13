<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaxiResource extends JsonResource
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
            'platenumber' => $this->plateNumber,
            'last_login_time' => $this->last_login_time,
            'last_logout_time' => $this->last_logout_time,
            'driver_id' => $this->driver_id,
            'created_at' => (string) $this->created_at,
            'updated_at' =>(string) $this->created_at,
            'owner' => $this->driver_owner
        ];
    }
}
