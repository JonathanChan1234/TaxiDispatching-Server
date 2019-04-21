<?php

namespace App;

use App\Transcation;
use App\RideShare;
use Illuminate\Database\Eloquent\Model;

class RideShareTransaction extends Model
{
    protected $table = 'ridesharetransactions';
    protected $fillable = ['first_transaction', 'second_transaction', 'driver_id', 'taxi_id'];

    public function transaction_one()
    {
        return $this->belongsTo(RideShare::class, "first_transaction");
    }

    public function transaction_two()
    {
        return $this->belongsTo(RideShare::class, "second_transaction");
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, "driver_id");
    }

    public function taxi()
    {
        return $this->belongsTo(Taxi::class, "taxi_id");
    }
}
