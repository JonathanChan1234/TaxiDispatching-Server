<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Transcation;

class UserResource extends JsonResource
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
            'phonenumber' => $this->phonenumber,
            'updated_at' =>(string) $this->created_at,
            'transcation' => $this->transcation::scopeOfUserCurrentTranscation($this->id)->orderBy('created_at')->take(1)->get()
        ];
    }
}
