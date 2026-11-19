<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The user's "Branch" is their assigned outlet (a locations row).
     *
     * users.branch_id pointed at the branches table and drove a branch-level
     * user scoping path that matched nothing in practice, so it is dropped.
     * users.location_id (the working outlet assignment, FK to locations) takes
     * over the branch_id name: a user's branch IS the outlet/location they
     * work at, and the FK to locations survives under the new name.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('location_id', 'branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('branch_id', 'location_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('status')
                ->constrained('branches')->nullOnDelete();
        });
    }
};
