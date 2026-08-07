<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(float $available, float $requested, string $quantityLabel = 'Requested Quantity')
    {
        parent::__construct("Insufficient Stock. Available Quantity: {$available}, {$quantityLabel}: {$requested}");
    }
}
