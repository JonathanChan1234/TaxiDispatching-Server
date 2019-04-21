<?php

namespace App;
use App\User;

use Illuminate\Database\Eloquent\Model;

class RideShare extends Model
{
    protected $table = "sharerides";
    protected $fillable = ['user_id', 'start_lat', 'start_long',
        'start_addr', 'des_lat', 'des_long',
        'des_addr', 'status', 'cancelled'];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

}
