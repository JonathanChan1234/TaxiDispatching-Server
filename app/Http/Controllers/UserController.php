<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator; 
use App\User;
use App\Http\Resources\UserResource;
use Config;
use Illuminate\Database\Eloquent\ModelNotFoundException; 

class UserController extends Controller
{   
    public function __construct()
    {
      $this->middleware('auth:api')->except(['register', 'login', 'respondWithToken']);
    }
    
    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'username' => 'max:100',
            'email' => 'required|email|unique:users',
            'phonenumber' => 'required|unique:users'
        ]);
        if($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        $user = User::create([
            'username'=> $request->username,
            'email'=> $request->email,
            'password'=> bcrypt($request->password),
            'phonenumber'=>$request->phonenumber,
        ]);
        $phonenumber = $request->phonenumber;
        if($request->hasFile('profileImg')) {
            if ($request->file('profileImg')->isValid()) {
                $path = $request->file('profileImg')->storeAs('public/userProfileImg', $phonenumber.'.jpg');
            }
        }
        $token = auth()->login($user);
        return $this->respondWithToken($token, $user);
    }

    public function login(Request $request) {
        $credentials = $request->only(['phonenumber','password']);
        // Config::set('jwt.user', 'App\User'); 
        // Config::set('auth.providers.users.model', \App\User::class);
        if(!$token = auth()->attempt($credentials)) {
            return response()->json(['error'=>"Unauthorized",
                                    'success' => 0],201);
        }
        try {
            $user = User::where('phonenumber', '=', $request->phonenumber)->firstorFail();
        } catch(ModelNotFoundException $e) {
            return response()->json([
                'success' => 0,
                'error' => "Username not found"], 200);
        }
        return $this->respondWithToken($token, $user);
    }

    protected function respondWithToken($token, $user) {
        return response()->json([
            'user' => $user,
            'success' => 1,
            'access_token' => $token,
            "token_type"=> 'bearer',
            'expires_in' => auth()->factory()->getTTL()*1000
        ]);
    }

    public function logout(Request $request) {
        Config::set('jwt.user', 'App\User'); 
		Config::set('auth.providers.users.model', \App\User::class);
        $user = auth()->user();
        auth()->logout();
        return response()->json($user,200);
    }
}
