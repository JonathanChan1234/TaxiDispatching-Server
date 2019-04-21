<?php

namespace App\Http\Controllers;

use App\User;
use App\Driver;
use App\RideShare;
use App\RideShareTransaction;

use App\Events\ShareRideDriverReachEvent;
use App\Events\ShareRidePassengerEvent;

use App\Http\Resources\DriverResource;
use App\Http\Resources\RideShareResource;
use App\Http\Resources\RideShareTransactionResource;

use App\Jobs\DriverShareRideReachJob;
use App\Jobs\RideSharingProcessor;

use App\Services\RatingService\AddRatingInterface;

use App\Utility\FCMHelper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RideShareController extends Controller
{
    public function __construct()
    {

    }

    public function makeRideShareRequest(Request $request)
    {
        $transcation = new RideShare;
        $transcation->user_id = $request->userid;
        $transcation->start_lat = $request->start_lat;
        $transcation->start_long = $request->start_long;
        $transcation->start_addr = $request->start_addr;
        $transcation->des_lat = $request->des_lat;
        $transcation->des_long = $request->des_long;
        $transcation->des_addr = $request->des_addr;
        $transcation->status = 100;
        $transcation->cancelled = 0;
        $transcation->save();
        RideSharingProcessor::dispatch($transcation);
        return new RideShareResource($transcation);
    }

    public function checkCurrentShareRideStatus(Request $request)
    {
        try {
            $transcation = RideShare::find($request->id);
            return new RideShareResource($transcation);
        } catch (ModelNotFoundException $e) {
            return null;
        }
    }

    public function getShareRidePairing(Request $request)
    {
        try {
            $transcation = RideShare::find($request->id);
            if ($transcation->rideshare_id != null) {
                $pairing = RideShareTransaction::find($transcation->rideshare_id);
                return response()->json([
                    'success' => 1,
                    'message' => "success",
                    'data' => new RideShareTransactionResource($pairing),
                ]);
            } else {
                return response()->json([
                    'success' => 0,
                    'message' => "no pairing",
                    'rideshare' => null,
                ]);
            }
        } catch (ModelNotFoundException $e) {
            return null;
        }
    }

    public function checkRideSharingTransaction(Request $request)
    {
        $transaction = RideShareTransaction::find($request->id);
        return new RideShareTransactionResource($transaction);
    }

    // id: share ride transaction id, driverId: driver id, response: 1
    public function driverResponseOrder(Request $request)
    {
        DB::beginTransaction();
        try {
            // Lock the two record first
            $shareRideTranscation = RideShareTransaction::find($request->id);
            $first_transcation_query = RideShare::where('id', $shareRideTranscation->first_transaction);
            $first_transcation = $first_transcation_query->lockForUpdate()->first();
            $second_transcation_query = RideShare::where('id', $shareRideTranscation->second_transaction);
            $second_transcation = $second_transcation_query->lockForUpdate()->first();

            $driver = Driver::find($request->driverId);

            //case 1: Both transactions are not cancelled
            if ($first_transcation->cancelled != 1 && $second_transcation->cancelled != 1) {
                if ($request->response == 1) {
                    $first_transcation->status = 200;
                    $first_transcation->save();

                    $second_transcation->status = 200;
                    $second_transcation->save();

                    $shareRideTranscation->status = 200;
                    $shareRideTranscation->save();
                    DB::commit();

                    $rideShareTransactionResource = new RideShareTransactionResource($shareRideTranscation);
                    $driverResource = new DriverResource($driver);
                    // Send to the two groups of passengers
                    event(new ShareRidePassengerEvent($rideShareTransactionResource, $driverResource, "shareRidePairingSuccess"));

                    $firstUser = User::find($first_transcation->user_id);
                    if ($firstUser->fcm_token != null) {
                        FCMHelper::pushMessagetoUser($firstUser->fcm_token,
                            "Share Ride Pairing Success",
                            ['passenger' => 'rideshare']);
                    }

                    $secondUser = User::find($second_transcation->user_id);
                    if ($secondUser->fcm_token != null) {
                        FCMHelper::pushMessagetoUser($secondUser->fcm_token,
                            "Share Ride Pairing Success",
                            ['passenger' => 'rideshare']);
                    }

                    return response()->json([
                        'success' => 1,
                        'response' => 'transaction success',
                    ]);
                } else {
                    $first_transcation->status = 100;
                    $first_transcation->rideshare_id = 0;
                    $first_transcation->save();

                    $second_transcation->status = 100;
                    $second_transcation->rideshare_id = 0;
                    $second_transcation->save();

                    $shareRideTranscation->status = 400;
                    $shareRideTranscation->save();

                    $driver->occupied = 0;
                    $driver->transcation_id = 0;
                    $driver->save();
                    DB::commit();

                    RideSharingProcessor::dispatch($first_transcation);
                    RideSharingProcessor::dispatch($second_transcation);

                    return response()->json([
                        'success' => 0,
                        'response' => 'rejected',
                    ]);
                }
            }

            // Case 2: first transaction cancelled and second transaction did not
            if ($first_transcation->cancelled == 1 && $second_transcation->cancelled != 1) {
                $second_transcation->status = 100;
                $second_transcation->rideshare_id = 0;
                $second_transcation->save();

                $shareRideTranscation->status = 400;
                $shareRideTranscation->save();

                $driver->occupied = 0;
                $driver->transcation_id = 0;
                $driver->save();

                DB::commit();

                RideSharingProcessor::dispatch($second_transcation);
                if ($request->response == 1) {
                    return response()->json([
                        'success' => 0,
                        'response' => 'cancelled',
                    ]);
                } else {
                    return response()->json([
                        'success' => 0,
                        'response' => 'rejected',
                    ]);
                }
            }

            // case 3: second transaction cancelled and first transaction did not
            if ($first_transcation->cancelled != 1 && $second_transcation->cancelled == 1) {
                $first_transcation->status = 100;
                $first_transcation->rideshare_id = 0;
                $first_transcation->save();

                $shareRideTranscation->status = 400;
                $shareRideTranscation->save();

                $driver->occupied = 0;
                $driver->transcation_id = 0;
                $driver->save();
                DB::commit();

                RideSharingProcessor::dispatch($first_transcation);
                if ($request->response == 1) {
                    return response()->json([
                        'success' => 0,
                        'response' => 'cancelled',
                    ]);
                } else {
                    return response()->json([
                        'success' => 0,
                        'response' => 'rejected',
                    ]);
                }
            }
            // case 4: both cancelled
            if ($first_transcation->cancelled == 1 && $second_transcation->cancelled == 1) {
                $shareRideTranscation->status = 400;
                $shareRideTranscation->save();

                $driver->occupied = 0;
                $driver->transcation_id = 0;
                $driver->save();
                DB::commit();

                if ($request->response == 1) {
                    return response()->json([
                        'success' => 0,
                        'response' => 'cancelled',
                    ]);
                } else {
                    return response()->json([
                        'success' => 0,
                        'response' => 'rejected',
                    ]);
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::info($e);
            return response()->json([
                'success' => -1,
                'message' => "fail",
            ]);
        }
    }

    /**
     * Driver reach the pick-up point
     */
    public function driverReachPickup(Request $request)
    {
        // Current time
        date_default_timezone_set('Asia/Hong_Kong');
        $t = time();
        $time = date("Y-m-d H:i:s", $t);

        $transaction = RideShareTransaction::find($request->id);

        if ($request->rideshareId == $transaction->first_transaction &&
            $transaction->first_confirmed == 100) {
            $transaction->firstReachTime = $time;
            $transaction->first_confirmed = 101;
            DriverShareRideReachJob::dispatch($transaction, 'first')->delay(now()->addMinute(5));
        } else if ($request->rideshareId == $transaction->second_transaction &&
            $transaction->second_confirmed == 100) {
            $transaction->secondReachTime = $time;
            $transaction->second_confirmed = 101;
            DriverShareRideReachJob::dispatch($transaction, 'second')->delay(now()->addMinute(5));
        }
        $transaction->save();

        // Send the message to the user
        $rideshare = RideShare::find($request->rideshareId);
        $user = User::find($rideshare->user_id);
        event(new ShareRideDriverReachEvent($transaction, $user->id, $time));
        if ($user->fcm_token != null) {
            FCMHelper::pushMessageToUser($user->fcm_token,
                'Your driver has reached the pick up point',
                [
                    'passenger' => 'rideshare',
                    'event' => 'driverReachPickup',
                    'time' => $time,
                ]);
        }
        return new RideShareTransactionResource($transaction);
    }

    /**
     * Driver cancel the transaction
     */
    public function driverCancelTransaction(Request $request)
    {
        // update the status of the ride transaction
        $transaction = RideShareTransaction::find($request->id);
        if ($request->rideshareId == $transaction->first_transaction
            && $transaction->first_confirmed == 102) {
            $transaction->first_confirmed = 400;
            $transaction->save();

            //Send the message to the user
            $rideshare = RideShare::find($request->rideshareId);
            $user = User::find($rideshare->user_id);
            FCMHelper::pushMessageToUser($user->fcm_token,
                'Your order was cancelled by the driver',
                ['passenger' => 'rideshare']);

            return response()->json([
                'success' => 1,
                'message' => 'order cancelled',
            ]);
        }
        if ($request->rideshareId == $transaction->second_transaction
            && $transaction->second_confirmed == 102) {
            $transaction->second_confirmed = 400;
            $transaction->save();

            //Send the message to the user
            $rideshare = RideShare::find($request->rideshareId);
            $user = User::find($rideshare->user_id);
            FCMHelper::pushMessageToUser($user->fcm_token,
                'Your order was cancelled by the driver',
                ['passenger' => 'rideshare']);

            return response()->json([
                'success' => 1,
                'message' => 'order cancelled',
            ]);
        }

        return response()->json([
            'success' => 0,
            'message' => 'Not ready',
        ]);
    }

    /**
     * Passenger confirm ride
     */
    public function passengerConfirmRide(Request $request)
    {
        $transaction = RideShareTransaction::find($request->id);
        if ($request->rideshareId == $transaction->first_transaction) {
            $transaction->first_confirmed = 200;
        }
        if ($request->rideshareId == $transaction->second_transaction
            && $transaction->second_confirmed == 102) {
            $transaction->second_confirmed = 200;
        }

        $transaction->save();
        $addRatingHelper = app(AddRatingInterface::class);
        $new_rating = $addRatingHelper->addRating(
            $transaction->driver_id,
            0,
            AddRatingInterface::RIDE_RATING,
            'Passenger Rating in share ride ' . $transaction->id);

        return response()->json([
            'success' => 1,
            'message' => 'confirmed',
        ]);
    }

    /**
     * Driver finish ride
     */
    public function driverFinishRide(Request $request)
    {
        $transaction = RideShareTransaction::find($request->id);

        // Both timeout and cancelled
        if ($transaction->first_confirmed == 102 &&
            $transaction->second_confirmed == 102) {
            $transaction->status = 400;
            $transaction->save();
            return response()->json([
                'success' => 1,
                'message' => "both timeout",
            ]);
        }

        //second timeout and first did not
        if ($transaction->first_confirmed == 200 &&
            $transaction->second_confirmed == 102) {
            $transaction->status = 300;
            $transaction->save();
            return response()->json([
                'success' => 1,
                'message' => "both timeout",
            ]);
        }

        if ($transaction->first_confirmed == 200 &&
            $transaction->second_confirmed == 400) {
            $transaction->status = 300;
            $transaction->save();
            return response()->json([
                'success' => 1,
                'message' => "both timeout",
            ]);
        }

        // first timeout and second did not
        if ($transaction->first_confirmed == 102 &&
            $transaction->second_confirmed == 200) {
            $transaction->status = 300;
            $transaction->save();
            return response()->json([
                'success' => 1,
                'message' => "both timeout",
            ]);
        }

        // first timeout and second did not
        if ($transaction->first_confirmed == 400 &&
            $transaction->second_confirmed == 200) {
            $transaction->status = 300;
            $transaction->save();
            return response()->json([
                'success' => 1,
                'message' => "both timeout",
            ]);
        }

        return response()->json([
            'success' => 0,
            'message' => "You are not allowed to finish the ride at this stage",
        ]);
    }

    /**
     * Passenger cancel the transaction
     */
    public function cancelTransaction(Request $request)
    {
        DB::beginTransaction();

        try {
            $transcationQuery = RideShare::where('id', $request->id);
            $transcation = $transcationQuery->lockForUpdate()->first();
            Log::info("id: " . $transcation->id);
            Log::info("Status: " . $transcation->status);
            switch ($transcation->status) {
                case 100: // The transaction is not processing yet
                    $transcation->cancelled = 1;
                    $transcation->status = 400;
                    $transcation->save();

                    DB::commit();
                    return response()->json([
                        'success' => 1,
                        'message' => "Cancel successfully",
                    ]);
                    break;
                case 101: //The transaction is processed and send to the driver
                    // Update the status of the user ride share (Ride Share)
                    $transcation->cancelled = 1;
                    $transcation->status = 400;
                    $transcation->save();

                    // Share Ride Transaction will be cancelled (Ride Share Transaction)
                    $rideShareTranscationQuery = RideShareTransaction::where('id', $transcation->rideshare_id);
                    $rideShareTranscation = $rideShareTranscationQuery->lockForUpdate()->first();
                    $rideShareTranscation->status = 400;
                    $rideShareTranscation->save();
                    DB::commit();

                    // Reset the driver status
                    $driver = Driver::find($rideShareTranscation->driver_id);
                    $driver->occupied = 0;
                    $driver->transcation_id = 0;

                    // Another ride share transaction will be searched again
                    if ($rideShareTranscation->first_transaction == $request->id) {
                        $paired_tranasction = RideShare::find($rideShareTranscation->second_transaction);
                    } else {
                        $paired_tranasction = RideShare::find($rideShareTranscation->first_transaction);
                    }
                    $paired_tranasction->status = 100;
                    $paired_tranasction->save();

                    // Send the notification to the driver
                    if ($driver->fcm_token != null) {
                        $message = "Share-Ride Transaction " . $rideShareTranscation->id . " has cancelled";
                        FCMHelper::pushMessageToUser(
                            $driver->fcm_token,
                            $message,
                            ['driver' => 'rideshare',
                                'event' => 'rideshareCancelled',
                                'id' => $rideShareTranscation->id]);
                    }

                    RideSharingProcessor::dispatch($paired_tranasction);
                    // notification to the driver that the transaction is cancelled
                    return response()->json([
                        'success' => 1,
                        'message' => "This transaction has been cancelled",
                    ]);
                    break;
                case 200:
                    // The transaction is paired up and accepted by the driver
                    // Update the status of the user ride share (ShareRide)
                    $transcation->cancelled = 1;
                    $transcation->status = 400;
                    $transcation->save();

                    // Update the status of the ride share transaction (RideShareTransaction)
                    $rideShareTranscationQuery = RideShareTransaction::where('id', $transcation->rideshare_id);
                    $rideShareTranscation = $rideShareTranscationQuery->lockForUpdate()->first();
                    $rideShareTranscation->first_confirmed = 400;
                    $rideShareTranscation->save();

                    DB::commit();

                    //Push notification to the driver
                    if ($rideShareTranscation->first_transaction == $request->id) {
                        // Passenger Group A cancelled the group
                        $message = "Passenger Group A cancelled the order";
                    } else {
                        $message = "Passenger Group B cancelled the order";
                    }

                    $driver = Driver::find($rideShareTranscation->driver_id);
                    if ($driver->fcm_token != null) {
                        FCMHelper::pushMessageToUser($driver->fcm_token,
                            $message,
                            ['driver' => 'rideshare',
                                'id' => $rideShareTranscation->id,
                                'event' => 'pairedTransactionCancelled']);
                    }

                    return response()->json([
                        'success' => 1,
                        'message' => "This transaction has been processed",
                    ]);
                    break;
                case 201:
                    DB::commit();
                    // notification to the driver that the transaction is cancelled
                    return response()->json([
                        'success' => 1,
                        'message' => "Cancel successfully",
                    ]);
                    break;
                default:
                    DB::commit();
                    return response()->json([
                        'success' => 0,
                        'message' => "Status not found",
                    ]);
                    break;
            }
        } catch (\Exception $e) { //Race condition or other error
            DB::rollback();
            Log::info($e);
            return response()->json([
                'success' => 0,
                'message' => "Illegal move",
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => 0,
                'message' => "Model not found error",
            ]);
        }
    }

    // Driver cancelled the transaction after pairing up
    public function driverExitTransaction(Request $request)
    {
        $transaction = RideShareTransaction::find($request->id);
        $transaction->first_confirmed = 400;
        $transaction->second_confirmed = 400;
        $transaction->status = 400;
        $transaction->save();

        $driver = Driver::find($transaction->driver_id);

        // Adjust rating
        $addRatingHelper = app(AddRatingInterface::class);
        $new_rating = $addRatingHelper->addRating(
            $driver->id,
            0,
            $addRatingHelper::CANCEL,
            'Passenger Rating in share ride ' . $transaction->id);

        $rides = DB::table('ridesharetransactions')
            ->join('sharerides', function ($join) {
                $join->on('sharerides.id', '=', 'ridesharetransactions.first_transaction')
                    ->orOn('sharerides.id', '=', 'ridesharetransactions.second_transaction');
            })
            ->where('ridesharetransactions.id', '=', $request->id)
            ->select('sharerides.id', 'sharerides.user_id')
            ->get();

        // Notification to the passengers
        foreach ($rides as $ride) {
            $user = User::find($ride->user_id);
            $shareRide = RideShare::find($ride->id);
            $shareRide->status = 400;
            if ($user->fcm_token != null) {
                FCMHelper::pushMessageToUser($user->fcm_token,
                    "The driver has cancelled the transaction",
                    ['passenger' => 'rideshare']);
            }
        }
    }

    public function testAPI(Request $request) {
        if($request->id != null) {
            $shareRide = RideShare::find($request->id);
            $shareRide->status = 400;
            $shareRide->save();
            return 'changed';
        }
        return 'error';
    }

    // Passenger Mobile Share Ride History
    public function checkPassengerOrder(Request $request)
    {
        $transactions = DB::table('sharerides')
            ->leftJoin('ridesharetransactions', 'sharerides.rideshare_id', '=', 'ridesharetransactions.id')
            ->leftJoin('drivers', 'drivers.id', '=', 'ridesharetransactions.driver_id')
            ->where('sharerides.user_id', '=', $request->id)
            ->select(
                'sharerides.id',
                'sharerides.start_addr',
                'sharerides.des_addr',
                'sharerides.updated_at as transaction_time',
                'ridesharetransactions.status',
                'ridesharetransactions.updated_at as rideshare_time',
                'drivers.id AS driver_id',
                'drivers.username',
                'drivers.phonenumber')
            ->get();
        return response()->json(['data' => $transactions]);
    }

    // Driver Mobile Share Ride History
    public function driverTransactionHistory(Request $request)
    {
        $transactions = RideShareTransaction::where("driver_id", "=", $request->id)
            ->orderBy("updated_at", "desc")
            ->paginate(100);
        return RideShareTransactionResource::collection($transactions);
    }

    // Web API
    public function shareRideHistory(Request $request)
    {
        $transactions = RideShareTransaction::orderBy("updated_at", "desc")
            ->paginate(100);
        return RideShareTransactionResource::collection($transactions);
    }

    // Web API
    public function shareRidePoolHistory(Request $request)
    {
        $transactions = RideShare::orderBy("updated_at", "desc")
            ->paginate(100);
        return RideShareResource::collection($transactions);
    }

    public function createSampleTransaction(Request $request)
    {
        $googleAPIClient = new Client(['base_uri' => 'https://maps.googleapis.com/maps/api/',
            'timeout' => 60]);
        try {
            $ride_position = array();
            $response = $googleAPIClient->get('place/nearbysearch/json',
                ['query' => 'location=22.314755558929125,114.16477605700494&radius=1500&key=AIzaSyBwJyQDS_1ZZfic_OLFdB0q7UZC11B9vw4']);
            if ($response->getStatusCode() == 200 && $response != null) {
                $content = json_decode($response->getBody()->getContents());
                if ($content->status == "OK") {
                    for ($i = 0; $i < 20; ++$i) {
                        $ride_position[$i]['start_lat'] = $content->results[$i]->geometry->location->lat;
                        $ride_position[$i]['start_lng'] = $content->results[$i]->geometry->location->lng;
                        $ride_position[$i]['start_addr'] = $content->results[$i]->name;
                    }
                } else {
                    echo "Route not found";
                }
            } else { //Empty body
                print_r("Request not found");
            }

            $response = $googleAPIClient->get('place/nearbysearch/json',
                ['query' => 'location=22.306249, 114.189161&radius=1500&key=AIzaSyBwJyQDS_1ZZfic_OLFdB0q7UZC11B9vw4']);
            if ($response->getStatusCode() == 200 && $response != null) {
                $content = json_decode($response->getBody()->getContents());
                if ($content->status == "OK") {
                    for ($i = 0; $i < 20; ++$i) {
                        $ride_position[$i]['des_lat'] = $content->results[$i]->geometry->location->lat;
                        $ride_position[$i]['des_lng'] = $content->results[$i]->geometry->location->lng;
                        $ride_position[$i]['des_addr'] = $content->results[$i]->name;
                    }
                } else {
                    echo "Route not found";
                }
            } else { //Empty body
                print_r("Request not found");
            }
            print_r($ride_position);
            for ($i = 0; $i < 20; ++$i) {
                $transcation = new RideShare;
                $transcation->user_id = 14;
                $transcation->start_lat = $ride_position[$i]['start_lat'];
                $transcation->start_long = $ride_position[$i]['start_lng'];
                $transcation->start_addr = $ride_position[$i]['start_addr'];
                $transcation->des_lat = $ride_position[$i]['des_lat'];
                $transcation->des_long = $ride_position[$i]['des_lng'];
                $transcation->des_addr = $ride_position[$i]['des_addr'];
                $transcation->cancelled = 0;
                $transcation->status = 100;
                $transcation->save();
            }
        } catch (ConnectException $e) { //Timeout error
            echo "connection error";
        } catch (ClientException $e1) { // 404 Error
            echo Psr7\str($e1->getRequest());
            echo Psr7\str($e1->getResponse());
        }
    }
}
