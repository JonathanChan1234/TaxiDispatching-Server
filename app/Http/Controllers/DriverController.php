<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Driver;
use App\Http\Resources\DriverResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use \base64;

class DriverController extends Controller
{
    public function __construct()
    {
      $this->middleware('auth:driver-api')->except(['register', 'login', 'respondWithToken', 'verifyPassword', 'setOccupied', 'resetDriver']);
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
            'occupied' => 0,
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
            $driver = Driver::where('phonenumber', '=', $request->phonenumber)->firstorFail();;
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
        $driver = auth('driver-api')->user();
        auth('driver-api')->logout();
        return response()->json($driver,200);
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

        if($request->occupied == 0) {
            $driver->occupied = 1;
            $driver->save();
            return response()->json([
                'success' => 1,
                'message' => "occupied"
            ]);
        } else {
            $driver->occupied = 0;
            $driver->save();
            return response()->json([
                'success' => 1,
                'message' => "unoccupied"]);
        }
    }

    public function resetDriver(Request $request) {
        $drivers = Driver::all();
        foreach($drivers as $driver) {
            $driver->occupied = 0;
            $driver->save();
        };
    }
}
