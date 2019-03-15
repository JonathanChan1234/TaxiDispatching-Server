<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Driver;

class Taxi extends Model
{
    protected $fillable = [
        'platenumber', 'occupied', 'password', 'owner', 'accessToken'
    ];

    public function driver() {
        return $this->belongsTo(Driver::class, "driver_id");
    }

    public function driver_owner() {
        return $this->belongsTo(Driver::class, "owner");
    }
}
