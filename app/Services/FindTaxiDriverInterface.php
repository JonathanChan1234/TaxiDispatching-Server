<?php
namespace App\Services;

interface FindTaxiDriverInterface {
    /**
     * Find the taxi driver for a specific transaction
     * @param Transcation transaction
     */
    public function findTaxiDriver($transaction);
}