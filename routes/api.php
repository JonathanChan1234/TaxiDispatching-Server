<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'api', 'middleware' => 'cors'],function(){
});

Route::post('user/register', 'UserController@register');
Route::post('user/login', 'UserController@login');
Route::post('user/logout', 'UserController@logout');

Route::post('driver/register', 'DriverController@register');
Route::post('driver/login', 'DriverController@login');
Route::post('driver/logout', 'DriverController@logout');
Route::post('driver/verifyPassoword', 'DriverController@verifyPassword');
Route::post('driver/setOccupied', 'DriverController@setOccupied');
Route::post('driver/resetDriver', 'DriverController@resetDriver');

Route::post('transcation/startTranscation', 'TranscationController@startTranscation');
Route::post('transcation/startTranscationDemo', 'TranscationController@startTranscationDemo');
Route::post('transcation/searchForRecentTranscation', 'TranscationController@searchForRecentTranscation');
Route::post('transcation/cancelOrder', 'TranscationController@cancelOrder');
Route::post('transcation/shareRide', 'TranscationController@startShareRide');
Route::post('transcation/driverReachPickupPoint', 'TranscationController@driverReachPickupPoint');

Route::post('taxi/checkDuplicate', 'TaxiController@checkDuplicate');
Route::post('taxi/register', 'TaxiController@register');
Route::post('taxi/signIn', 'TaxiController@signIn');
Route::post('taxi/deleteAccount', 'TaxiController@deleteTaxiAccount');
Route::post('taxi/checkOwnerTaxi', 'TaxiController@findOwnedTaxi');
Route::post('taxi/signInQRCode', 'TaxiController@signInQRCode');
Route::post('taxi/logout', 'TaxiController@logout');