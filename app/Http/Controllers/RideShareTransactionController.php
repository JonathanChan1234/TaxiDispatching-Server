<?php

namespace App\Http\Controllers;

use App\RideShareTransaction;
use Illuminate\Http\Request;

class RideShareTransactionController extends Controller
{
    public function makeRideSharingTransaction(Request $request) {
        $transaction = new RideShareTransaction;
    }
}
