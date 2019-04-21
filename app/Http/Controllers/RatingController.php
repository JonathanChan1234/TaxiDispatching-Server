<?php

namespace App\Http\Controllers;

use App\Rating;
use App\Transcation;
use App\RideShareTransaction;
use Illuminate\Http\Request;
use App\Services\RatingService\AddRatingInterface;
use App\Services\RatingService\AddRatingService;
use Illuminate\Support\Facades\Log;

class RatingController extends Controller
{
    public function rateDriver(Request $request)
    {
        $addRatingHelper = app(AddRatingInterface::class);
        $transcation = Transcation::find($request->transcation);
        if($transcation == null) {
            $new_rating = $addRatingHelper->addRating(
                $request->id, 
                $request->rating, 
                $addRatingHelper::RIDE_RATING, 
                'Passenger Rating in share ride ');
        } else {
            $new_rating = $addRatingHelper->addRating(
                $request->id, 
                $request->rating, 
                $addRatingHelper::RIDE_RATING, 
                'Passenger Rating in personal ride ' . $transcation->id);
        }
        return $new_rating;
    }
}
