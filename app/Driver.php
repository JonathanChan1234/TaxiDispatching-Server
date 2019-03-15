<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Transcation;
use App\Rating;
use App\Taxi;

class Driver extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = [
        'username', 'email', 'password', 'phonenumber', 'occupied'
    ];

    protected $hidden = [
        'password'
    ];
    
    public function taxi() {
        return $this->belongsTo(Taxi::class, "taxi_id");
    }

    public function transcation() {
        return $this->hasMany(Transcation::class);
    }

    public function rating() {
        return $this->hasMany(Rating::class);
    }

    public function getJWTIdentifier() {
        return $this->getKey();
    }
  
    public function getJWTCustomClaims() {
        return [];
    }
}
