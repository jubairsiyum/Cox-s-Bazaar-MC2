<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    /**
     * Create an exception for a stock operation that would leave negative stock.
     */
    public static function for(string $productName, int $available, int $requested, ?string $locationName = null): self
    {
        if ($locationName !== null) {
            return new self(
                "Insufficient stock for '{$productName}' at {$locationName}: requested {$requested}, only {$available} available there."
            );
        }

        return new self(
            "Insufficient stock for '{$productName}': requested {$requested}, available {$available}."
        );
    }
}
