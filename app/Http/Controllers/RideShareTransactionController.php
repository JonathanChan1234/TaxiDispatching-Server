<?php

namespace App\Http\Controllers;

use App\RideShareTransaction;
use App\Http\Resources\RideShareTransactionResource;
use Illuminate\Http\Request;

class RideShareTransactionController extends Controller
{
    public function checkRideSharingTransaction(Request $request) {
        $transaction = RideShareTransaction::find($request->id);
        return new RideShareTransactionResource($transaction);
    }
}
