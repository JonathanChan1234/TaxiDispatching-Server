<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Transcation;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = [
        'username', 'email', 'password', 'phonenumber',
    ];

    protected $hidden = [
        'password', 'remember_token'
    ];

    public function transcations() {
        return $this->hasMany(Transcation::class);
    }

    public function getJWTIdentifier() {
        return $this->getKey();
    }
  
    public function getJWTCustomClaims() {
        return [];
    }
}
