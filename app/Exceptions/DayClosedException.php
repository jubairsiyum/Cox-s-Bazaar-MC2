<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class DayClosedException extends RuntimeException
{
    public static function forDate(string $date, ?string $branchName = null): self
    {
        $target = $branchName !== null ? "for {$branchName}" : '';

        return new self("Transactions on {$date} {$target} are locked because the business day has been closed. Contact Super Admin to reopen.");
    }
}
