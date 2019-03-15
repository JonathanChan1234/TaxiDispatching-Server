<?php

namespace App;

use App\Transcation;
use Illuminate\Database\Eloquent\Model;

class RideShareTransaction extends Model
{
    protected $table = 'ridesharetransactions';
    protected $fillable = ['first_transaction', 'second_transaction', 'driver_id', 'taxi_id'];

    public function transaction_one()
    {
        return $this->belongsTo(Transcation::class, "first_transaction");
    }

    public function transaction_two()
    {
        return $this->belongsTo(Transcation::class, "second_transaction");
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
