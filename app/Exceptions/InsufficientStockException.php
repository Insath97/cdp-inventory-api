<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(float $available, float $requested)
    {
        parent::__construct("Insufficient Stock. Available Quantity: {$available}, Requested Quantity: {$requested}");
    }
}
