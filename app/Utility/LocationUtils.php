<?php
namespace App\Utility;

use GuzzleHttp\Client;

class LocationUtlis
{
    /**
     * Haversine Formula
     * Reference: http://www.codecodex.com/wiki/Calculate_Distance_Between_Two_Points_on_a_Globe#PHP
     */
    public static function getDistance($latitude1, $longitude1, $latitude2, $longitude2)
    {
        $earth_radius = 6371;

        $dLat = deg2rad($latitude2 - $latitude1);
        $dLon = deg2rad($longitude2 - $longitude1);

        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * asin(sqrt($a));
        $d = $earth_radius * $c;

        return $d;
    }

    public static function findDrivingTimeTwoPoint($latitude1, $longitude1, $latitude2, $longitude2)
    {
        $googleAPIClient = new Client(['base_uri' => 'https://maps.googleapis.com/maps/api/',
            'timeout' => 60]);
        $query = "origin=$latitude1,$longitude1&destination=$latitude2,$longitude2&key=AIzaSyBwJyQDS_1ZZfic_OLFdB0q7UZC11B9vw4";
        try {
            $response = $googleAPIClient->get('directions/json',
                ['query' => $query]);
            if ($response->getStatusCode() == 200 && $response != null) {
                $content = json_decode($response->getBody()->getContents());
                if ($content->status == "OK") {
                    return ($content->routes->legs->duration->value) / 60;
                } else {
                    return -1;
                }
            } else {
                return -1;
            }

        } catch (ConnectException $e) { //Timeout error
            return 0;
        } catch (ClientException $e1) { // 404 Error
            echo Psr7\str($e1->getRequest());
            echo Psr7\str($e1->getResponse());
        }
    }

    public static function findDrivingTimeManyPoint($latitude1, $longitude1,
        $latitude2, $longitude2,
        $latitude3, $longitude3) {

        $googleAPIClient = new Client(['base_uri' => 'https://maps.googleapis.com/maps/api/',
            'timeout' => 60]);
        $query = "origin=$latitude1,$longitude1&waypoints=" . "
        $latitude2,$longitude2&destination=$latitude3,$longitude3&key=AIzaSyBwJyQDS_1ZZfic_OLFdB0q7UZC11B9vw4";
        try {
            $response = $googleAPIClient->get('directions/json',
                ['query' => $query]);
            if ($response->getStatusCode() == 200 && $response != null) {
                $content = json_decode($response->getBody()->getContents());
                if ($content->status == "OK") {
                    return ($content->routes->legs->duration->value) / 60;
                } else {
                    return -1;
                }
            } else {
                return -1;
            }

        } catch (ConnectException $e) { //Timeout error
            return 0;
        } catch (ClientException $e1) { // 404 Error
            echo Psr7\str($e1->getRequest());
            echo Psr7\str($e1->getResponse());
        }
    }
}
