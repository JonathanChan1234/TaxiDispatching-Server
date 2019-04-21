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
Route::post('user/refreshFCMToken', 'UserController@refreshFCMToken');

Route::post('driver/register', 'DriverController@register');
Route::post('driver/login', 'DriverController@login');
Route::post('driver/logout', 'DriverController@logout');
Route::post('driver/verifyPassoword', 'DriverController@verifyPassword');
Route::post('driver/setOccupied', 'DriverController@setOccupied');
Route::post('driver/setOccupiedType', 'DriverController@setOccupiedType');
Route::post('driver/resetDriver', 'DriverController@resetDriver');
Route::post('driver/findCurrentTransaction', 'DriverController@findCurrentTransaction');
Route::post('driver/refreshFCMToken', 'DriverController@refreshFCMToken');
Route::post('driver/getDriverPicture', 'DriverController@getDriverPicture');
Route::get('driver/retrieveDriverInformation', 'DriverController@retrieveDriverInformation');

Route::post('transcation/startTranscation', 'TranscationController@startTranscation');
Route::post('transcation/startTranscationDemo', 'TranscationController@startTranscationDemo');
Route::post('transcation/searchForRecentTranscation', 'TranscationController@searchForRecentTranscation');
Route::post('transcation/searchPassengerCurrentTransaction', 'TranscationController@searchPassengerCurrentTransaction');

Route::post('transcation/cancelOrder', 'TranscationController@cancelOrder');
Route::post('transcation/driverResponseOrder', 'TranscationController@driverResponseOrder');
Route::post('transcation/passengerConfirmOrder', 'TranscationController@passengerConfirmOrder');


Route::post('transcation/shareRide', 'TranscationController@startShareRide');
Route::post('transcation/driverExitOrder', 'TranscationController@driverExitOrder');
Route::post('transcation/driverReachPickupPoint', 'TranscationController@driverReachPickupPoint');
Route::post('transcation/driverCancelOrder', 'TranscationController@driverCancelOrder');
Route::post('transcation/finishRide', 'TranscationController@finishRide');
Route::post('transcation/passengerConfirmRide ', 'TranscationController@confirmRide');
Route::post('transcation/passengerTimeout', 'TranscationController@passengerTimeout');
Route::post('transcation/testFCM', 'TranscationController@testFCM');
Route::post('transcation/checkDriverOrder', 'TranscationController@checkDriverOrder');
Route::post('transcation/checkPassengerOrder', 'TranscationController@checkPassengerOrder');
Route::get('transcation/retrieveLatestTrasnaction', 'TranscationController@retrieveLatestTrasnaction');
Route::get('transcation/retrieveLatest100Trasnaction', 'TranscationController@retrieveLatest100Trasnaction');

Route::post('rideShare/makeShareRide', 'RideShareController@makeRideShareRequest');
Route::post('rideShare/checkStatus', 'RideShareController@checkCurrentShareRideStatus');
Route::post('rideShare/driverResponseOrder', 'RideShareController@driverResponseOrder');
Route::post('rideShare/cancelOrder', 'RideShareController@cancelTransaction');
Route::post('rideShare/getShareRidePairing', 'RideShareController@getShareRidePairing');
Route::post('rideShare/checkTransactionStatus', 'RideShareController@checkRideSharingTransaction');
Route::post('rideShare/driverReachPickup', 'RideShareController@driverReachPickup');
Route::post('rideShare/driverExitRide', 'RideShareController@driverExitTransaction');
Route::post('rideShare/driverFinishRide', 'RideShareController@driverFinishRide');
Route::post('rideShare/passengerConfirmRide', 'RideShareController@passengerConfirmRide');

Route::post('rideShare/driverTransactionHistory', 'RideShareController@driverTransactionHistory');
Route::post('rideShare/checkPassengerOrder', 'RideShareController@checkPassengerOrder');

Route::get('rideShare/createSampleTransaction', 'RideShareController@createSampleTransaction');
Route::get('rideShare/shareRideHistory', 'RideShareController@shareRideHistory');
Route::get('rideShare/shareRidePoolHistory', 'RideShareController@shareRidePoolHistory');

Route::post('taxi/checkDuplicate', 'TaxiController@checkDuplicate');
Route::post('taxi/register', 'TaxiController@register');
Route::post('taxi/signIn', 'TaxiController@signIn');
Route::post('taxi/deleteAccount', 'TaxiController@deleteTaxiAccount');
Route::post('taxi/checkOwnerTaxi', 'TaxiController@findOwnedTaxi');
Route::post('taxi/signInQRCode', 'TaxiController@signInQRCode');
Route::post('taxi/logout', 'TaxiController@logout');

Route::post('rating/rateDriver', 'RatingController@rateDriver');

Route::get('/send_test_email', function(){
	Mail::raw('Sending emails with Mailgun and Laravel is easy!', function($message)
	{
		$message->to('u3527394@connect.hku.hk');
	});
});

// Test API
Route::get('rideShare/testAPI', 'RideShareController@testAPI');

