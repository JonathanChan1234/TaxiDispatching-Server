<?php
namespace App\Services\FindTaxiDriver;

interface FindTaxiDriverInterface {
    /**
     * Find the taxi driver for a specific transaction
     * type: p (personal)/ s(share ride)
     * transactionId: id
     */
    public function findTaxiDriver($transaction, $type);
}