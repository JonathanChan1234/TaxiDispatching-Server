<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Driver;
use App\Http\Resources\DriverResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;  

class DriverController extends Controller
{
    public function __construct()
    {
      $this->middleware('auth:driver-api')->except(['register', 'login', 'respondWithToken', 'verifyPassword']);
    }

    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'username' => 'max:100',
            'email' => 'required|email|unique:drivers',
            'phonenumber' => 'required|unique:drivers'
        ]);
        if($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        $driver = Driver::create([
            'username'=> $request->username,
            'email'=> $request->email,
            'password'=> bcrypt($request->password),
            'phonenumber'=>$request->phonenumber,
            'occupied'=>0,
        ]);
        if($request->hasFile('profileImg')) {
            if ($request->file('profileImg')->isValid()) {
                $path = $request->file('profileImg')->storeAs('public/driverProfileImg', $phonenumber.'.jpg');
            }
        }
        $token = auth()->login($driver);
        return $this->respondWithToken($token);
    }
    public function login(Request $request) {
        $credentials = $request->only(['phonenumber','password']);
        if(!$token = auth('driver-api')->attempt($credentials)) {
            return response()->json([
                'error'=>"Unauthorized",
                'success' => 0], 201);
        }
        try {
            $driver = Driver::where('phonenumber', '=', $request->phonenumber)->firstorFail();;
        } catch(ModelNotFoundException $e) {
            return response()->json([
                'success' => 0,
                'error' => "Username not found"]);
        }
        return $this->respondWithToken($token, $driver);
    }

    protected function respondWithToken($token, $driver) {
        return response()->json([
            'success' => 1,
            'username' => $driver->username,
            'access_token' => $token,
            "token_type"=> 'bearer', 
            'expires_in' => auth()->factory()->getTTL()*60
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
                'error' => "User not found"]);
        }
        if(password_verify($request->password, $driver->password)) {
            return response()->json([
                'success' => 1,
                'error' => "Correct"]);
        } 
        return response()->json([
            'success' => 0,
            'error' => "Incorrect password"]);
    }
}
