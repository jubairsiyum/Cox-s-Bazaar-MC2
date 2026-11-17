<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    /**
     * Create an exception for a stock operation that would leave negative stock.
     */
    public static function for(string $productName, int $available, int $requested): self
    {
        return new self(
            "Insufficient stock for '{$productName}': requested {$requested}, available {$available}."
        );
    }
}
