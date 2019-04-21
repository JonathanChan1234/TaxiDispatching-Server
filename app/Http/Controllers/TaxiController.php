<?php

namespace App\Http\Controllers;

use App\Taxi;
use App\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\TaxiResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;  

use \Datetime;
use \DateTimeZone;

class TaxiController extends Controller
{
    /**
     * Check whether there is duplicated taxi
     * Taxi Registration
     */
    public function checkDuplicate(Request $request) {
        $validator = Validator::make($request->all(), [
            'platenumber' => 'max:100|unique:taxis'
        ]);
        if($validator->fails()) {
            return response()->json([
                'success' => 0,
                'message' => $validator->errors()], 200);
        }
        return response()->json([
            'success' => 1,
            'message' => 'No Duplicate'], 200);
    }

    /**
     * Registration for a new taxi after verification
     */
    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'platenumber' => 'max:10|unique:taxis'
        ]);
        if($validator->fails()) {
            return response()->json([
                'success' => 0,
                'message' => $validator->errors()], 200);
        } 
        $taxi = Taxi::create([
            'platenumber' => $request->platenumber,
            'password' => bcrypt($request->password),
            'occupied' => 0,
            'owner' => $request->id,
            'accessToken' => md5(uniqid(rand(), true))
        ]);
        return response()->json([
            'success' => 1,
            'message' => $taxi->accessToken
        ]);
    }

    /**
     * Sign in for taxi
     * ocuupied: 0->1
     */
    public function signIn(Request $request) {
        try {
            $taxi = Taxi::where('platenumber', '=', $request->platenumber)->firstorFail();
        } catch(ModelNotFoundException $e) {
            return response()->json([
                'success' => 0,
                'taxi' => NULL,
                'message' => "Taxi not found"]);
        }
        //If the access token is correct and it is not signed by anyone
        if($request->accessToken == $taxi->accessToken && $taxi->occupied == 0) {
            //Update the status of the taxi account
            $currentTime = new DateTime('now');
            $currentTime->setTimezone(new DateTimeZone('Asia/Hong_Kong'));
            $taxi->last_login_time = $currentTime->format('Y-m-d H:i:s');
            // $taxi->occupied = 1;
            $taxi->driver_id = $request->id;
            $taxi->save();
            
            // Update the status of the driver
            $driver = Driver::find($request->id);
            $driver->occupied = 1;
            $driver->taxi_id = $taxi->id;
            $driver->save();
            return response()->json([
                'success' => 1,
                'taxi' => new TaxiResource($taxi),
                'message' => strval($taxi->id)]);
        } else if($taxi->occupied == 1) {   // The taxi is currently signed in by someone
            return response()->json([
                'success' => 0,
                'taxi' => NULL,
                'message' => "This taxi is already signed up by others"]);
        }
        return response()->json([
            'success' => 0,
            'taxi' => NULL,
            'message' => "Incorrect password"]);    //Incorrect password
    }

    public function logout(Request $request) {
        try {
            $taxi = Taxi::find($request->taxiID);
            $driver = Driver::find($request->driverID);
        } catch(ModelNotFoundException $e) {
            return response()->json([
                'success' => 0,
                'message' => "Taxi not found"]);
        }
        $taxi->occupied = 0;
        $taxi->driver_id = 0;
        $taxi->save();
        
        $driver->occupied = 1;
        $driver->taxi_id = 0;
        $driver->save();
        return response()->json([
            'success' => 1,
            'message' => "Success"]);
    }

    /**
     * list the taxi owned by a user
     */
    public function findOwnedTaxi(Request $request) {
        try {
            $taxi_collection = Taxi::where('owner', $request->id)->get();
        } catch(ModelNotFoundException $e) {
            return response()->json([
                'owned_taxis' => []]);
        }
        return response()-> json([
            'owned_taxis' => TaxiResource::collection($taxi_collection)
        ]);
    }

    /**
     * Delete a taxi account (must be deleted by owner)
     */
    public function deleteTaxiAccount(Request $request) {
        try {
            $taxi = Taxi::where('platenumber', '=', $request->platenumber)->firstorFail();
        } catch(ModelNotFoundException $e) {
            return response()->json([
                'success' => 0,
                'message' => "Taxi not found"]);
        }
        if($taxi->occupied == 1) {
            $driver = Driver::find($taxi->driver_id);
            return response()->json([
                'success' => 0,
                'message' => $driver->username." is currently taking this taxi"
            ]);
        }
        if(password_verify($request->password, $taxi->password)) {
            $taxi->delete();
            return response()->json([
                'success' => 1,
                'message' => "Taxi account has been removed"]);
        }
    }

    /**
     * Sign In (Taxi QR Code Generator)
     */
    public function signInQRCode(Request $request) {
        try {
            $taxi = Taxi::where('platenumber', '=', $request->account)->firstorFail();
        } catch(ModelNotFoundException $e) {
            return response()->json([
                'success' => 0,
                'message' => "Taxi not found"]);
        }
        if(password_verify($request->password, $taxi->password)) {
            return response()->json([
                'success' => 1,
                'message' => "Success"]);
        } else {
            return response()->json([
                'success' => 0,
                'message' => "Incorrect Password"]);
        }
    }
}
