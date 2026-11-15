<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity columns per table, in the same order the application checks them.
     *
     * @var array<string, list<string>>
     */
    private const IDENTITY_COLUMNS = [
        'customers' => ['name', 'mobile', 'tel_no', 'email'],
        'suppliers' => ['name', 'mobile', 'tel_no', 'email', 'tax_number'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::IDENTITY_COLUMNS as $table => $columns) {
            $this->normalizeIdentityValues($table, $columns);
            $this->removeRedundantRecords($table, $columns);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('name');
            $table->unique('mobile');
            $table->unique('tel_no');
            $table->unique('email');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->unique('name');
            $table->unique('mobile');
            $table->unique('tel_no');
            $table->unique('email');
            $table->unique('tax_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropUnique(['mobile']);
            $table->dropUnique(['tel_no']);
            $table->dropUnique(['email']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropUnique(['mobile']);
            $table->dropUnique(['tel_no']);
            $table->dropUnique(['email']);
            $table->dropUnique(['tax_number']);
        });
    }

    /**
     * Trim and blank-out identity values so the unique indexes compare the way the application does.
     *
     * @param  list<string>  $columns
     */
    private function normalizeIdentityValues(string $table, array $columns): void
    {
        foreach (DB::table($table)->select(array_merge(['id'], $columns))->get() as $row) {
            $updates = [];

            foreach ($columns as $column) {
                $value = $row->{$column};

                if ($value === null) {
                    continue;
                }

                $normalized = trim((string) $value);

                if ($column === 'name') {
                    $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
                }

                if ($column === 'email') {
                    $normalized = mb_strtolower($normalized);
                }

                $normalized = $normalized === '' ? null : $normalized;

                if ($normalized !== $value) {
                    $updates[$column] = $normalized;
                }
            }

            if ($updates !== []) {
                DB::table($table)->where('id', $row->id)->update($updates);
            }
        }
    }

    /**
     * Keep the earliest record of each duplicate group and permanently remove the rest.
     *
     * @param  list<string>  $columns
     */
    private function removeRedundantRecords(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            $groups = [];

            foreach (DB::table($table)->select(['id', $column])->whereNotNull($column)->orderBy('id')->get() as $row) {
                $groups[mb_strtolower((string) $row->{$column})][] = (int) $row->id;
            }

            foreach ($groups as $ids) {
                if (count($ids) < 2) {
                    continue;
                }

                $keepId = array_shift($ids);

                foreach ($ids as $duplicateId) {
                    $this->repointDependents($table, $duplicateId, $keepId);
                    DB::table($table)->where('id', $duplicateId)->delete();
                }
            }
        }
    }

    /**
     * Move linked records of a redundant row onto the row that is kept.
     */
    private function repointDependents(string $table, int $duplicateId, int $keepId): void
    {
        if ($table === 'suppliers' && Schema::hasTable('supplier_opening_payments')) {
            DB::table('supplier_opening_payments')
                ->where('supplier_id', $duplicateId)
                ->update(['supplier_id' => $keepId]);
        }
    }
};
