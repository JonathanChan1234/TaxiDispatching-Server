<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Driver;
use App\RideShareTransaction;
use App\Transcation;
use App\Rating;

use App\Http\Resources\DriverResource;
use App\Http\Resources\RideShareTransactionResource;
use App\Http\Resources\TranscationResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use \base64;
use Illuminate\Support\Facades\Storage;

class DriverController extends Controller
{
    public function __construct()
    {
      $this->middleware('auth:driver-api')->except([
          'register', 'login', 'respondWithToken',
           'verifyPassword', 'setOccupied', 'resetDriver',
           'findCurrentTransaction', 'logout', 'retrieveDriverInformation',
           'getDriverPicture']);
    }

    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'username' => 'max:100',
            'email' => 'required|email|unique:drivers',
            'phonenumber' => 'required|unique:drivers'
        ]);
        if($validator->fails()) {
            return response()->json([
                'user' => NULL,
                'success' => 0,
                'access_token' => NULL,
                "token_type"=> NULL,
                'expires_in' => NULL,
                'message' => $validator->errors()->all()
            ], 200);
        }
        $driver = Driver::create([
            'username'=> $request->username,
            'email'=> $request->email,
            'password'=> bcrypt($request->password),
            'phonenumber'=> $request->phonenumber,
            'fcm_token' => $request->token,
            'occupied' => 0,
        ]);
        $baseRating = Rating::create([
            'driver_id' => $driver->id,
            'rating' => 3,
            'eventLog' => 'Base rating'
        ]);
        if($request->img != "") {
            $url = storage_path()."\\app\public\driverProfileImg\\". $request->phonenumber.'.png';
            file_put_contents($url, base64_decode($request->img));
        }
        $token = auth()->login($driver);
        return $this->respondWithToken($token, $driver);
    }
    
    public function login(Request $request) {
        $credentials = $request->only(['phonenumber','password']);
        if(!$token = auth('driver-api')->attempt($credentials)) {
            return response()->json([
                'user' => NULL,
                'success' => 0,
                'access_token' => NULL,
                "token_type"=> NULL,
                'expires_in' => NULL,
                'message' => ["Incorrect Password"]
            ], 200);
        }
        try {
            $driver = Driver::where('phonenumber', '=', $request->phonenumber)->firstorFail();
            $driver->fcm_token = $request->token;
            $driver->save();
        } catch(ModelNotFoundException $e) {
            return response()->json([
                'user' => NULL,
                'success' => 0,
                'access_token' => NULL,
                "token_type"=> NULL,
                'expires_in' => NULL,
                'message' => ["Account not found"]
            ], 200);
        }
        return $this->respondWithToken($token, $driver);
    }

    protected function respondWithToken($token, $driver) {
        return response()->json([
            'success' => 1,
            'user' => $driver,
            'access_token' => $token,
            "token_type"=> 'bearer', 
            'expires_in' => auth()->factory()->getTTL()*60,
            'message' => []
        ]);
    }

    public function logout(Request $request) {
        $user = Driver::find($request->id);
        $user->fcm_token = '';
        $user->save();
        return response()->json([
            'success' => 1,
            'message' => 'success'
        ]);
    }

    public function verifyPassword(Request $requst) {
        try {
            $driver = Driver::where('id', '=', $request->id)->firstorFail();
        } catch(ModelNotFoundException $e) {
            return response()->json([
                'success' => 0,
                'message' => "User not found"]);
        }
        if(password_verify($request->password, $driver->password)) {
            return response()->json([
                'success' => 1,
                'message' => "Correct"]);
        } 
        return response()->json([
            'success' => 0,
            'message' => "Incorrect password"]);
    }

    public function setOccupied(Request $request) {
        try {
            $driver = Driver::where('id', '=', $request->id)->firstorFail();
        } catch(ModelNotFoundException $e) {
            return response()->json([
                'success' => 0,
                'message' => "User not found"]);
        }
        $driver->transcation_id = 0;
        if($request->occupied == 0) {
            $driver->occupied = 1;
            $driver->save();
            return response()->json([
                'success' => 1,
                'message' => "occupied"
            ]);
        } else {
            if($request->type != null) {
                $driver->ride_available = $request->type;
            }
            $driver->occupied = 0;
            $driver->save();
            return response()->json([
                'success' => 1,
                'message' => "unoccupied"]);
        }
    }

    public function findCurrentTransaction(Request $request) {
        try {
            $driver = Driver::find($request->id);
            $transcationId = $driver->transcation_id;
            $transcationType = $driver->transcation_type;

            if($transcationId != 0 && $driver->taxi_id != 0) {
                return response()->json([
                    'success' => 1,
                    'occupied' => $driver->occupied,
                    'type' => $transcationType,
                    'id' => $transcationId
                ]);
            } else {
                return response()->json([
                    'success' => 0,
                    'occupied' => $driver->occupied,
                    'type' => null,
                    'transcation' => null
                ]);
            }
        } catch(\Exception $e) {
            return response()->json([
                'success' => 0,
                'occupied' => $driver->occupied,
                'type' => null,
                'transcation' => null]);
        }
    }

    public function searchDriverTransaction(Request $request) {
        
    }

    public function getDriverPicture(Request $request) {
        $url = Storage::disk('public')->url(
            'driverProfileImg/59364214.png'
        );
        return $url;
    }

    public function resetDriver(Request $request) {
        $drivers = Driver::all();
        foreach($drivers as $driver) {
            $driver->occupied = 0;
            $driver->transcation_id = 0;
            $driver->save();
        };
    }

    public function refreshFCMToken(Request $request) {
        $user = Driver::find($request->id);
        $user->fcm_token = $request->token;
        $user->save();
        return response()->json([
            'success' => 1,
            'message' => 'update successfully'
        ]);
    }

    public function retrieveDriverInformation(Request $request) {
        $rating = DB::table('drivers')
            ->leftJoin('ratings', 'ratings.driver_id', '=', 'drivers.id')
            ->groupBy('drivers.id')
            ->orderBy('drivers.id', 'desc')
            ->select('drivers.*', DB::raw('AVG(ratings.rating) as rating'))
            ->get();
        return json_encode($rating);
    }
}
