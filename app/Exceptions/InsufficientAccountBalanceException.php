<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientAccountBalanceException extends RuntimeException
{
    /**
     * Create an exception for a money operation that would overdraw an account.
     */
    public static function for(string $accountName, float $available, float $requested): self
    {
        return new self(
            "Insufficient balance in '{$accountName}': requested {$requested}, available {$available}."
        );
    }
}
