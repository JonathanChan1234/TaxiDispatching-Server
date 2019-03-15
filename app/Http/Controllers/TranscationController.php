<?php

namespace App\Http\Controllers;

use App\Events\PassengerDriverReachEvent;
use App\Http\Resources\TranscationResource;
use App\Jobs\DriverReachPickupJob;
use App\Jobs\RideSharingProcessor;
use App\Jobs\TransactionProcessor;
use App\Jobs\TranscationJob;
use App\Transcation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TranscationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['startTranscation',
            'searchForRecentTranscation', 'cancelOrder', 'startTranscationDemo', 'driverReachPickupPoint',
            'startShareRide']);
        $this->middleware('auth:driver-api')->except(['startTranscation',
            'searchForRecentTranscation', 'cancelOrder', 'startTranscationDemo', 'driverReachPickupPoint',
            'startShareRide']);
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
        $transcation->type = $request->type;
        $transcation->meet_up_time = $request->meet_up_time;
        $transcation->save();

        if ($transcation->type == 'r') {
            RideSharingProcessor::dispatch($transcation);
        } else {
            TransactionProcessor::dispatch($transcation);
        }
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
        $transcation->type = 'p';
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
            $transcation = Transcation::where([['status', '<', 300], ['id', '=', $request->id]])
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

    public function startShareRide(Request $request)
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
        $transcation->type = 's';
        $transcation->cancelled = 0;
        $transcation->meet_up_time = $request->meet_up_time;
        $transcation->save();
        RideSharingProcessor::dispatch($transcation);
        return new TranscationResource($transcation);
    }

    /**
     * Handle Cancel Order request from the passenger
     * Pessimistic Lock
     */
    public function cancelOrder(Request $request)
    {
        DB::beginTransaction();

        try {
            $transcationQuery = Transcation::find($request->id);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => 0,
                'message' => "Model not found error",
            ]);

            $transcation = $transcationQuery->lockForUpdate()->first();
            switch ($transcation->status) {
                case 100: // The transaction is not processing yet
                    $transcation->cancelled = 1;
                    $transcation->status = 400;
                    $transcation->save();
                    return response()->json([
                        'success' => 1,
                        'message' => "Cancel successfully",
                    ]);
                    break;
                case 101: //The transaction is processed and send to the driver
                    $transcation->cancelled = 1;
                    $transcation->status = 400;
                    $transcation->save();
                    // notification to the driver that the transaction is cancelled
                    return response()->json([
                        'success' => 1,
                        'message' => "Cancel successfully",
                    ]);
                    break;
                case 102:
                    // The transaction is accepted by the driver
                    // The passenger has to be fined to cancel the deal
                    return response()->json([
                        'success' => 0,
                        'message' => "This transaction has been processed",
                    ]);
                    break;
                case 200:
                    return response()->json([
                        'success' => 0,
                        'message' => "This transaction has been processed",
                    ]);
                    break;
            }
            DB::commit();
        } catch (\Exception $e) { //Race condition or other error
            DB::rollback();
            return response()->json([
                'success' => 0,
                'message' => "Illegal move",
            ]);
        }
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
        if ($transcation->cancelled == 0 
                && $transcation->status == 200) {
            // Return the time to the driver (for internal counter)
            date_default_timezone_set('Asia/Hong_Kong');
            $t = time();
            $time = date("Y-m-d H:i:s", $t);

            $transcation->status = 201;
            $transcation->driverReachTime = $time;
            $transcation->save();

            // This job will be run after 5 minutes
            DriverReachPickupJob::dispatch()->delay(now()->addMinute(5));
            
            // Find the passenger
            event(new PassengerDriverReachEvent($driver, $transcation, $time));
            return response()->json([
                'success' => 1,
                'time' => $time,
            ]);
        } else {
            return response()->json([
                'success' => 0,
                'message' => "Transaction has been cancelled",
            ]);
        }
    }
}
