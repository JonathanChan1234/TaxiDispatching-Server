<?php

namespace App\Services\RatingService;

use App\Rating;

/**
 * Adjust the rating of the driver
 */
class AddRatingService implements AddRatingInterface
{
    /**
     * Adjust the rating according to different event
     */
    public function addRating($driver, $rating, $type, $eventLog)
    {
        switch ($type) {
            case AddRatingService::RIDE_RATING:

                $rating_change = 0;
                switch ($rating) {
                    case 0:
                        $rating_change = 0.01;
                        break;
                    case 1:
                        $rating_change = -0.06;
                        break;
                    case 2:
                        $rating_change = -0.02;
                        break;
                    case 3:
                        $rating_change = 0;
                        break;
                    case 4:
                        $rating_change = 0.02;
                        break;
                    case 5:
                        $rating_change = 0.04;
                        break;
                    default:
                        $rating_change = 0;
                        break;
                }

                $average = Rating::where('driver_id', '=', $driver)
                    ->avg('rating');
                $count = Rating::where('driver_id', '=', $driver)
                    ->count();
                if (($average + $rating_change) > 0) {
                    $new_rating = ($count + 1) * ($average + $rating_change) - ($average * $count);
                    Rating::create([
                        'driver_id' => $driver,
                        'rating' => $new_rating,
                        'eventLog' => $eventLog,
                    ]);
                    return $new_rating;
                }
                return 0;
                break;

            case AddRatingService::REJECT_RIDE:
                $average = Rating::where('driver_id', '=', $driver)
                    ->avg('rating');
                $count = Rating::where('driver_id', '=', $driver)
                    ->count();
                if (($average + (-0.01)) > 0) {
                    $new_rating = ($count + 1) * ($average + (-0.01)) - ($average * $count);
                    Rating::create([
                        'driver_id' => $driver,
                        'rating' => $new_rating,
                        'eventLog' => $eventLog,
                    ]);
                    return $new_rating;
                }
                return 0;
                break;
            case AddRatingService::NO_SHOW:
                $average = Rating::where('driver_id', '=', $driver)
                    ->avg('rating');
                $count = Rating::where('driver_id', '=', $driver)
                    ->count();
                if (($average + (-0.5)) > 0) {
                    $new_rating = ($count + 1) * ($average + (-0.5)) - ($average * $count);
                    Rating::create([
                        'driver_id' => $driver,
                        'rating' => $new_rating,
                        'eventLog' => $eventLog,
                    ]);
                    return $new_rating;
                }
                return 0;
                break;

            case AddRatingService::CANCEL:
                $average = Rating::where('driver_id', '=', $driver)
                    ->avg('rating');
                $count = Rating::where('driver_id', '=', $driver)
                    ->count();
                if (($average + (-0.5)) > 0) {
                    $new_rating = ($count + 1) * ($average + (-0.5)) - ($average * $count);
                    Rating::create([
                        'driver_id' => $driver,
                        'rating' => $new_rating,
                        'eventLog' => $eventLog,
                    ]);
                    return $new_rating;
                }
                return 0;
                break;
                
            default:
                return 0;
                break;
        }
    }
}
