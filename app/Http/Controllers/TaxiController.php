<?php

namespace App\Http\Controllers;

use App\Taxi;
use App\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\TaxiResource;

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
        $driver = Taxi::create([
            'platenumber' => $request->platenumber,
            'password' => bcrypt($request->password),
            'occupied' => 0,
            'owner' => $request->id
        ]);
        return response()->json([
            'success' => 1,
            'message' => 'Successfully register for Taxi '.$request->platenumber
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
                'message' => "Taxi not found"]);
        }
        //If the password is correct and it is not signed by anyone
        if(password_verify($request->password, $taxi->password) && $taxi->occupied == 0) {
            //Update the status of the taxi account
            $taxi->last_login_time = $request->time;
            $taxi->occupied = 1;
            $taxi->driver_id = $request->id;
            $taxi->save();
            // Update the status of the driver
            $driver = Driver::find($request->id);
            $driver->occupied = 0;
            $driver->taxi_id = $taxi->id;
            $driver->save();
            return response()->json([
                'success' => 1,
                'message' => "Correct"]);
        } else if($taxi->occupied == 1) {   // The taxi is currently signed in by someone
            return response()->json([
                'success' => 0,
                'message' => "This taxi is alreay signed up by others"]);
        }
        return response()->json([
            'success' => 0,
            'message' => "Incorrect password"]);    //Incorrect password
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
        // return response()-> json([
        //     'owned_taxis' => $taxi_collection
        // ]);
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
                'error' => "Taxi not found"]);
        }
        if(password_verify($request->password, $taxi->password)) {
            $taxi->delete();
            return response()->json([
                'success' => 1,
                'error' => "Taxi account has been removed"]);
        }
    }
}
