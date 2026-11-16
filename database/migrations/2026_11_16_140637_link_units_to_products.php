<?php

declare(strict_types=1);

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('id')->constrained('products')->restrictOnDelete();
            $table->unique('product_id');
        });

        // Link every existing stock row to a product, reusing a product with the
        // same name when it has no stock row yet and creating one otherwise.
        $usedProductIds = DB::table('units')->whereNotNull('product_id')->pluck('product_id')->all();

        foreach (DB::table('units')->whereNull('product_id')->orderBy('id')->get() as $row) {
            $productId = DB::table('products')
                ->where('name', $row->product_name)
                ->whereNotIn('id', $usedProductIds)
                ->orderBy('id')
                ->value('id');

            if (! $productId) {
                $productId = Product::create([
                    'name' => $row->product_name,
                    'status' => 'active',
                    'created_by' => $row->created_by,
                ])->id;
            }

            DB::table('units')->where('id', $row->id)->update(['product_id' => $productId]);
            $usedProductIds[] = $productId;
        }

        // Every product gets a stock row so it shows up in Units and Transfers.
        $linkedProductIds = DB::table('units')->pluck('product_id')->filter()->all();

        foreach (DB::table('products')->whereNotIn('id', $linkedProductIds)->orderBy('id')->get() as $product) {
            DB::table('units')->insert([
                'product_id' => $product->id,
                'quantity' => 0,
                'unit' => 'Pcs',
                'created_by' => $product->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // The product name now lives on products only.
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex('units_product_name_index');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('product_name')->nullable()->index()->after('id');
        });

        foreach (DB::table('units')->orderBy('id')->get() as $row) {
            $name = DB::table('products')->where('id', $row->product_id)->value('name');

            DB::table('units')->where('id', $row->id)->update(['product_name' => $name ?? 'Unknown product']);
        }

        Schema::table('units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
