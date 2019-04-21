<?php
namespace App\Services\RatingService;

interface AddRatingInterface {
    public function addRating($driver, $rating, $type, $eventLog);

    // Constants
    const RIDE_RATING = "rideRating";
    const REJECT_RIDE = "rejectRide";
    const NO_SHOW = "noshow";
    const CANCEL = "cancel";
}