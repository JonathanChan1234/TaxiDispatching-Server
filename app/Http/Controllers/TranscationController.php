<?php

namespace App\Http\Controllers;

use App\Driver;
use App\RideShare;
use App\Transcation;
use App\User;
use app\RideShareTransaction;
use App\Events\PassengerDriverReachEvent;
use App\Events\PassengerNotificationEvent;

use App\Http\Resources\TranscationResource;

use App\Jobs\DriverReachPickupJob;
use App\Jobs\PassengerTimeoutJob;
use App\Jobs\ResumeDriverStatus;
use App\Jobs\TranscationJob;
use App\Jobs\TranscationTimeoutJob;

use App\Services\RatingService\AddRatingInterface;
use App\Services\FindTaxiDriver\FindTaxiDriverInterface;
use App\Services\FindTaxiDriver\NoDriverFoundException;

use App\Utility\FCMHelper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TranscationController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:api')->except(['startTranscation',
        //     'searchForRecentTranscation', 'cancelOrder', 'startTranscationDemo', 'driverReachPickupPoint',
        //     'startShareRide']);
        // $this->middleware('auth:driver-api')->except(['startTranscation',
        //     'searchForRecentTranscation', 'cancelOrder', 'startTranscationDemo', 'driverReachPickupPoint',
        //     'startShareRide']);
    }

    /**
     * Transaction for demo purpose
     * Always find driver with id = 4
     */
    public function startTranscation(Request $request)
    {
        $transcation = new Transcation;
        $transcation->user_id = $request->userid;
        $transcation->start_lat = $request->start_lat;
        $transcation->start_long = $request->start_long;
        $transcation->start_addr = $request->start_addr;
        $transcation->des_lat = $request->des_lat;
        $transcation->des_long = $request->des_long;
        $transcation->des_addr = $request->des_addr;
        $transcation->status = 100;
        $transcation->cancelled = 0;
        $transcation->meet_up_time = $request->meet_up_time;
        $transcation->save();
        // TransactionProcessor::dispatch($transcation);
        TranscationJob::dispatch($transcation);
        TranscationTimeoutJob::dispatch($transcation)->delay(now()->addMinutes(5));
        return new TranscationResource($transcation);
    }

    /**
     * Transaction request
     * follow the algorithm
     */
    public function startTranscationDemo(Request $request)
    {
        $transcation = new Transcation;
        $transcation->user_id = $request->userid;
        $transcation->start_lat = $request->start_lat;
        $transcation->start_long = $request->start_long;
        $transcation->start_addr = $request->start_addr;
        $transcation->des_lat = $request->des_lat;
        $transcation->des_long = $request->des_long;
        $transcation->des_addr = $request->des_addr;
        $transcation->status = 100;
        $transcation->cancelled = 0;
        $transcation->meet_up_time = $request->meet_up_time;
        $transcation->save();
        //Start Processing Transcation
        TranscationJob::dispatch($transcation);
        return new TranscationResource($transcation);
    }

    public function searchForRecentTranscation(Request $request)
    {
        try {
            $transcation = Transcation::find($request->id)
                ->orderBy('updated_at', 'desc')
                ->firstOrFail();
            return new TranscationResource($transcation);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => 0,
                'message' => "There is no ongoing transaction"], 400);
        }
        return null;
    }

    public function searchPassengerCurrentTransaction(Request $request)
    {
        try {
            $personal_ride = Transcation::where('user_id', '=', $request->id)
                ->where('status', '<', 300)
                ->orderBy('updated_at', 'desc')
                ->firstOrFail();
            return response()->json([
                'success' => 1,
                'type' => 'personal',
                'id' => $personal_ride->id], 200);
        } catch (ModelNotFoundException $e) {
            try {
                $share_ride = RideShare::where('user_id', "=", $request->id)
                    ->where('status', '<=', 200)
                    ->where('cancelled', '=', 0)
                    ->orderBy('updated_at', 'desc')
                    ->firstOrFail();
                if($share_ride->rideshare_id != null) {
                    $share_ride_transaction = RideShareTransaction::find($share_ride->rideshare_id);
                    if($share_ride_transaction->status < 200) {
                        return response()->json([
                            'success' => 1,
                            'type' => 'share',
                            'id' => $share_ride->id], 200);
        
                    } else {
                        return response()->json([
                            'success' => 0,
                            'type' => "share ride finished",
                            'id' => null], 200);
                    }
                } 
                return response()->json([
                    'success' => 1,
                    'type' => 'share',
                    'id' => $share_ride->id], 200);

            } catch (ModelNotFoundException $e) { // Cannot find share ride ride
                return response()->json([
                    'success' => 0,
                    'type' => "no share ride",
                    'id' => null], 200);
            }
        } catch (ModelNotFoundException $e1) { // Cannot find personal ride
            return response()->json([
                'success' => 0,
                'type' => "no personal ride",
                'id' => null], 200);
        }

        return response()->json([
            'success' => 0,
            'type' => null,
            'id' => null], 200);
    }

    public function driverResponseOrder(Request $request)
    {
        $transcation = Transcation::find($request->transcationId);
        $driver = Driver::find($request->driverId);
        $response = $request->response;

        //If the driver accept the order
        if ($response == 1 &&
            $transcation->driver_id == $driver->id &&
            $transcation->status == 101) {
            // update the status of the driver
            $transcation->status = 102;
            $transcation->driver_id = $driver->id;
            $transcation->taxi = $driver->taxi_id;
            $transcation->save();

            date_default_timezone_set('Asia/Hong_Kong');
            $t = time();
            $time = date("Y-m-d H:i:s", $t);
            // Push notification to the passenger (on driver found)
            event(new PassengerNotificationEvent($driver, $transcation, $time, "passengerDriverFound"));
            PassengerTimeoutJob::dispatch($transcation, $driver)->delay(now()->addMinutes(3));
            return response()->json([
                'success' => 1,
                'message' => "Proceed successfully",
            ]);
        } else if ($response == 0 &&
            $transcation->driver_id == $driver->id &&
            $transcation->status == 101) {

            // restore the driver availability
            $driver->occupied = 0;
            $driver->transcation_id = 0;
            $driver->save();

            $transcation->status = 100;
            $transcation->save();
            //Search another driver
            TranscationJob::dispatch($transcation);

            // Adjust rating
            $addRatingHelper = app(AddRatingInterface::class);
            $new_rating = $addRatingHelper->addRating(
                $driver->id,
                0,
                $addRatingHelper::REJECT_RIDE,
                'Passenger Rating in personal ride ' . $transcation->id);

            return response()->json([
                'success' => 1,
                'message' => "Proceed successfully",
            ]);
        } else {
            if ($transcation->driver_id != $driver->id) {
                return response()->json([
                    'success' => 0,
                    'message' => "Timeout",
                ]);
            }

            if ($transcation->status == 400) {
                return response()->json([
                    'success' => 0,
                    'message' => "Cancelled",
                ]);
            }
        }

        return response()->json([
            'success' => 0,
            'message' => "Timeout",
        ]);
    }

    public function passengerConfirmOrder(Request $request)
    {
        $transcation = Transcation::find($request->transcationId);
        // restore the driver status
        $driver = Driver::find($transcation->driver_id);

        if ($request->response == 1) {
            // update the status of the driver and transaction
            if ($transcation->status != 400) {
                $transcation->status = 200;
                $transcation->save();
                if ($driver->fcm_token != null) {
                    // Send the FCM message to the driver
                    FCMHelper::pushMessageToUser($driver->fcm_token,
                        "Passegner Confirm the Ride",
                        ['driver' => 'transcation']);
                }
                return response()->json([
                    'success' => 1,
                    'message' => 'proceed',
                ]);
            } else {
                if ($driver->fcm_token != null) {
                    // Send the FCM message to the driver
                    FCMHelper::pushMessageToUser($driver->fcm_token,
                        "Timeout",
                        ['driver' => 'transcation']);
                }
                return response()->json([
                    'success' => 0,
                    'message' => 'timeout',
                ]);
            }
        } else {
            $transcation->status = 400;
            $transcation->cancelled = 1;
            $transcation->save();

            if ($driver->fcm_token != null) {
                // Send the FCM message to the driver
                FCMHelper::pushMessageToUser($driver->fcm_token,
                    "Timeout",
                    ['driver' => 'cancelled']);
            }

            return response()->json([
                'success' => 0,
                'message' => 'cancelled',
            ]);
        }
    }

    public function driverTimeout(Request $request)
    {
        $transcation = Transcation::find($request->id);
        $driver = Driver::find($request->driverId);
        ResumeDriverStatus::dispatch($transcation, $driver);
    }

    public function passengerTimeout(Request $request)
    {
        try {
            $transcation = Transcation::find($request->id);
            $transcation->status = 400;
            $transcation->save();
            return response()->json([
                'success' => 1,
                'message' => "Timeout cancelled"], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => 0,
                'message' => "There is no ongoing transaction"], 200);
        }
    }

    /**
     * Handle Cancel Order request from the passenger
     * Pessimistic Lock
     * TO-DO: Add cancel event
     */
    public function cancelOrder(Request $request)
    {

        DB::beginTransaction();

        try {
            $transcationQuery = Transcation::where('id', $request->id);
            $transcation = $transcationQuery->lockForUpdate()->first();
            $driver = Driver::find($transcation->driver_id);
            switch ($transcation->status) {

                case 100: // The transaction is not processing yet
                    $this->cancel($transcation, $driver);
                    DB::commit();
                    return response()->json([
                        'success' => 1,
                        'message' => "Cancel successfully",
                    ]);
                    break;

                case 101: //The transaction is processed and send to the driver
                    $this->cancel($transcation, $driver);
                    DB::commit();

                    // notification to the driver that the transaction is cancelled
                    if ($driver->fcm_token != null) {
                        FCMHelper::pushMessageToUser($driver->fcm_token, "Transaction Cancelled",
                            ['driver' => 'transcation']);
                    }

                    return response()->json([
                        'success' => 1,
                        'message' => "Cancel successfully",
                    ]);
                    break;

                case 102:
                    // The transcation is accepted by the driver
                    $this->cancel($transcation, $driver);
                    DB::commit();

                    // notification to the driver that the transaction is cancelled
                    if ($driver->fcm_token != null) {
                        FCMHelper::pushMessageToUser($driver->fcm_token, "Transaction Cancelled",
                            ['driver' => 'transcation']);
                    }
                    DB::commit();
                    return response()->json([
                        'success' => 1,
                        'message' => "Cancel successfully",
                    ]);
                    break;

                case 200: // Both confirmed,  have to pay fine?
                    $this->cancel($transcation, $driver);
                    DB::commit();
                    if ($driver->fcm_token != null) {
                        FCMHelper::pushMessageToUser($driver->fcm_token, "Transaction Cancelled",
                            ['driver' => 'transcation']);
                    }

                    return response()->json([
                        'success' => 1,
                        'message' => "Cancel successfully",
                    ]);
                    break;
                case 201:
                    $this->cancel($transcation, $driver);
                    DB::commit();
                    if ($driver->fcm_token != null) {
                        FCMHelper::pushMessageToUser($driver->fcm_token, "Transaction Cancelled",
                            ['driver' => 'transcation']);
                    }
                    // notification to the driver that the transaction is cancelled
                    return response()->json([
                        'success' => 1,
                        'message' => "Cancel successfully",
                    ]);
                    break;
                case 202:
                    $this->cancel($transcation, $driver);
                    DB::commit();
                    if ($driver->fcm_token != null) {
                        FCMHelper::pushMessageToUser($driver->fcm_token, "Transaction Cancelled",
                            ['driver' => 'transcation']);
                    }
                    // notification to the driver that the transaction is cancelled
                    return response()->json([
                        'success' => 1,
                        'message' => "Cancel successfully",
                    ]);
                    break;
                default:
                    DB::commit();
                    return response()->json([
                        'success' => 1,
                        'message' => "Status not found",
                    ]);
                    break;
            }
        } catch (\Exception $e) { //Race condition or other error
            DB::rollback();
            Log::Info($e);
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

    public function cancel($transcation, $driver)
    {
        $transcation->cancelled = 1;
        $transcation->status = 400;
        $transcation->save();
    }

    public function driverReachPickupPoint(Request $request)
    {
        try {
            $transcation = Transcation::find($request->id);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => 0,
                'message' => "Model not found error",
            ]);
        }
        Log::Info("Cancelled: " . $transcation->cancelled);
        Log::Info("Status: " . $transcation->status);
        if ($transcation->cancelled == 0
            && $transcation->status == 200) {
            $driver = Driver::find($transcation->driver_id);
            // Return the time to the driver (for internal counter)
            date_default_timezone_set('Asia/Hong_Kong');
            $t = time();
            $time = date("Y-m-d H:i:s", $t);

            $transcation->status = 201;
            $transcation->driverReachTime = $time;
            $transcation->save();

            // This job will be run after 5 minutes
            DriverReachPickupJob::dispatch($transcation)->delay(now()->addMinute(5));

            // Find the passenger
            event(new PassengerDriverReachEvent($driver, $transcation, $time));
            return response()->json([
                'success' => 1,
                'message' => $time,
            ]);
        } else {
            return response()->json([
                'success' => 0,
                'message' => "Transaction has been cancelled",
            ]);
        }
    }

    public function driverExitOrder(Request $request)
    {
        $transcation = Transcation::find($request->id);
        if ($transcation->status >= 200) {
            $transcation->status = 400;
            $transcation->save();

            // Reset the driver status
            $driver = Driver::find($transcation->driver_id);
            $driver->transcation_id = 0;
            $driver->save();

            // Send the message to the passenger
            $user = User::find($transcation->user_id);
            FCMHelper::pushMessageToUser($user->fcm_token,
                "Your driver suddenly cancel the order",
                ['passenger' => 'transcation']);

            // Adjust rating
            $addRatingHelper = app(AddRatingInterface::class);
            $new_rating = $addRatingHelper->addRating(
                $driver->id,
                0,
                $addRatingHelper::REJECT_RIDE,
                'Passenger Rating in personal ride ' . $transcation->id);

            return response()->json([
                'success' => 1,
                'message' => 'cancel successfully',
            ]);
        }
        return response()->json([
            'success' => 0,
            'message' => 'You cannot cancel at this stage',
        ]);
    }

    public function driverCancelOrder(Request $request)
    {
        $transcation = Transcation::find($request->id);
        if ($transcation->status == 202) {
            $transcation->status = 400;
            $transcation->save();

            // Reset the driver status
            $driver = Driver::find($transcation->driver_id);
            $driver->transcation_id = 0;
            $driver->save();

            FCMHelper::pushMessageToUser($user->fcm_token,
                "Your order is cancelled",
                ['passenger' => 'transcation']);
            return response()->json([
                'success' => 1,
                'message' => 'cancel successfully',
            ]);
        }
        return response()->json([
            'success' => 0,
            'message' => 'You cannot cancel at this stage',
        ]);
    }

    public function confirmRide(Request $request)
    {
        $transcation = Transcation::find($request->id);
        if ($transcation->status >= 200 && $transcation->status != 400) {
            $transcation->status = 300;
            $transcation->save();

            $addRatingHelper = app(AddRatingInterface::class);
            $new_rating = $addRatingHelper->addRating(
                $transcation->driver_id,
                0,
                AddRatingInterface::RIDE_RATING,
                'Passenger Rating in personal ride ' . $transcation->id);

            $user = User::find($transcation->user_id);
            FCMHelper::pushMessageToUser($user->fcm_token,
                "Passenger has confirmed the ride",
                ['passenger' => 'transaction']);

            return response()->json([
                'success' => 1,
                'message' => 'finished ride',
            ]);
        } else {
            return response()->json([
                'success' => 0,
                'message' => 'the ride has been cancelled',
            ]);
        }
    }

    public function finishRide(Request $request)
    {
        $transcation = Transcation::find($request->id);
        if ($transcation->status == 300) {
            // finish the ride
            $transcation->status = 301;
            $transcation->save();

            // Reset the driver status
            $driver = Driver::find($transcation->driver_id);
            $driver->transcation_id = 0;
            $driver->save();

            // Send the bill to the passenger
            $user = User::find($transcation->user_id);
            FCMHelper::pushMessageToUser($user->fcm_token,
                "The driver has sent you the bill",
                ['passenger' => 'transaction']);

            return response()->json([
                'success' => 1,
                'message' => 'finished ride',
            ]);
        } else {
            return response()->json([
                'success' => 0,
                'message' => 'your passenger has to first confirm the ride',
            ]);
        }
    }

    public function testFCM(Request $request)
    {
        $findDriverHelper = app(FindTaxiDriverInterface::class);
        try {
            $driver = $findDriverHelper->findTaxiDriver(421, 'p');
            return $driver;
        } catch(NoDriverFoundException $e) {
            return 0;
        }
       
        // FCMHelper::pushMessageToUser("f4X753JV60A:APA91bFEkb8z6mbA1kaOANMPlvJPiyBavxXQSLdgSmO4abGkjO_aVxsDMk4ZM9RTfe7SJcsZr8KupFEpK1Iqs75JZ6zg6VCiLxxuwnJm1C1JeD34PXDqws86-UTpqAp8FlUjmquKI9RJ",
        //     "hello test2", []);
    }

    // order list for passenger
    public function checkPassengerOrder(Request $request)
    {
        $orders = Transcation::where("user_id", "=", $request->id)->orderBy('updated_at', 'desc')->paginate(50);
        return TranscationResource::collection($orders);
    }

    // order list for driver
    public function checkDriverOrder(Request $request)
    {
        $orders = Transcation::where("driver_id", "=", $request->id)->orderBy('updated_at', 'desc')->get();
        return TranscationResource::collection($orders);
    }

    //API for demo website
    public function retrieveLatestTrasnaction(Request $request)
    {
        $transactions = Transcation::orderBy('updated_at', 'desc')->paginate(10);
        return TranscationResource::collection($transactions);
    }

    public function retrieveLatest100Trasnaction(Request $request)
    {
        $transactions = Transcation::orderBy('updated_at', 'desc')->paginate(100);
        return TranscationResource::collection($transactions);
    }
}
