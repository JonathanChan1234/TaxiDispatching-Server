<?php

use Illuminate\Foundation\Inspiring;
use App\Events\PushNotification;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

Artisan::command('notification', function () {
    broadcast(new PushNotification());
    $this->comment("news sent");
})->describe('Send news');
