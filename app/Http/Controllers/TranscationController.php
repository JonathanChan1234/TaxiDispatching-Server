<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;  
use Illuminate\Http\Request;
use App\Transcation;
use App\Http\Resources\TranscationResource;
use App\Jobs\SleepTask;
use App\Jobs\TransactionProcessor;


class TranscationController extends Controller
{
    public function __construct()
    {
      $this->middleware('auth:api')->except(['startTranscation', 'searchForRecentTranscation', 'cancelOrder']);
      $this->middleware('auth:driver-api')->except(['startTranscation', 'searchForRecentTranscation', 'cancelOrder']);
    }

    public function startTranscation(Request $request) {
      $transcation = new Transcation;
      $transcation->user_id = $request->userid;
      $transcation->start_lat = $request->start_lat;
      $transcation->start_long = $request->start_long;
      $transcation->start_addr = $request->start_addr;
      $transcation->des_lat = $request->des_lat;
      $transcation->des_long = $request->des_long;
      $transcation->des_addr = $request->des_addr;
      $transcation->status = 100;
      $transcation->meet_up_time = $request->meet_up_time;
      $transcation->save();
      //Start Processing Transcation
      TransactionProcessor::dispatch($transcation);
      return new TranscationResource($transcation);
    }

    public function searchForRecentTranscation(Request $request) {
      try {
        $transcation = Transcation::where([['status', '<', 300], ['user_id', '=', $request->userid]])
        ->orderBy('updated_at', 'desc')
        ->firstOrFail();
      } catch(ModelNotFoundException $e) {
          return response()->json([
            'success' => 0,
            'message' => "There is no ongoing transaction"], 400);
      }
      return new TranscationResource($transcation);
    }

    public function cancelOrder(Request $request) {
      try {
        $transcation = Transcation::find($request->id);
      } catch(ModelNotFoundException $e) {
        return response()->json([
          'success' => 0,
          'message' => "Model not found error"
        ]);
      }
      if($transcation != null) {
        $transcation->status = 400;
        $transcation->save();
        return response()->json([
          'success' => 1,
          'message' => "Cancel successfully"
        ]);
      }
      return response()->json([
      'success' => 0,
      'message' => "Model not found error"
      ]);
    }
}
