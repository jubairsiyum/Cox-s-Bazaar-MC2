<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Accounts provisioned by an administrator (or seeded) are trusted and must
     * never be blocked by the email verification wall. Legacy rows carried
     * invalid zero dates which normalised to NULL and left users unverified.
     */
    public function up(): void
    {
        // Normalise legacy MySQL zero dates first.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE users SET email_verified_at = NULL WHERE CAST(email_verified_at AS CHAR) LIKE '0000%'");
        }

        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Nothing sensible to undo: the previous state was invalid zero dates.
    }
};
