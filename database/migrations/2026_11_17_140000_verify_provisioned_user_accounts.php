<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Safety net for accounts created outside the admin UI (tinker, ad-hoc
     * seeders) before provisioned accounts were auto-verified: nobody should be
     * locked out behind the email verification wall.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Nothing sensible to undo: unverified provisioned accounts were a bug.
    }
};
