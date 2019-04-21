<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transcation extends Model
{   
    protected $primaryKey = 'id';
    protected $fillable = ['user_id', 'start_lat', 'start_long',
                            'start_addr', 'des_lat', 'des_long',
                            'des_addr', 'status', 'cancelled'];

    // dynamic scope to check the current transcation of the user
    public function scopeOfUserCurrentTranscation($query, $user_id) {
        return $query->where('status', '<', 500)    //500: transcation closed/finished
                    ->where('user_id', $user_id);
    }

    // dynamic scope to check the current transcation of the driver
    public function scopeOfDriverCurrentTranscation($query, $driver_id) {
        return $query->where('status', '<', 500)     //500: transcation closed/finished
                    ->where('driver_id', $driver_id);
    }

    public function user() {
        return $this->belongsTo(User::class, "user_id");
    }

    public function driver() {
        return $this->belongsTo(Driver::class, "driver_id");
    }

    public function taxi() {
        return $this->belongsTo(Taxi::class, "taxi");
    }
}
