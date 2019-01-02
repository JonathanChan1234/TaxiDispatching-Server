<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = ['driver_id', 'rating'];
    
    public function driver() {
        return $this->belongsTo(Driver::class);
    }
}
