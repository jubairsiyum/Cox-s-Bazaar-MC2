<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Product;
use App\Models\Unit;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class UnitImport implements SkipsEmptyRows, ToModel, WithHeadingRow, WithValidation
{
    private int $importedCount = 0;

    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): ?Unit
    {
        $productName = trim((string) ($row['product_name'] ?? $row['product'] ?? ''));

        if ($productName === '') {
            return null;
        }

        $storage = trim((string) ($row['storage'] ?? ''));
        $color = trim((string) ($row['color'] ?? ''));
        $quantity = (int) ($row['quantity'] ?? 0);
        $unit = $this->normalizeUnit($row['unit'] ?? null);
        $note = isset($row['note']) ? trim((string) $row['note']) : null;

        $this->importedCount++;

        // An existing variant gets its quantity updated instead of a duplicate row.
        $product = Product::query()
            ->matchingVariant($productName, $storage ?: null, $color ?: null)
            ->first();

        if ($product instanceof Product) {
            $stockRow = $product->stockRow
                ?? $product->stockRow()->create(['quantity' => 0, 'unit' => $unit, 'created_by' => Auth::id()]);

            StockService::adjust($stockRow, $quantity, $note);

            return null;
        }

        $product = Product::create([
            'name' => $productName,
            'storage' => $storage ?: null,
            'color' => $color ?: null,
            'status' => 'active',
            'created_by' => Auth::id(),
        ]);

        return new Unit([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit' => $unit,
            'note' => $note,
            'created_by' => Auth::id(),
        ]);
    }

    public function rules(): array
    {
        return [
            '*.product_name' => ['required_without:*.product'],
            '*.product' => ['required_without:*.product_name'],
            '*.storage' => ['nullable', 'string', 'max:100'],
            '*.color' => ['nullable', 'string', 'max:100'],
            '*.quantity' => ['nullable', 'integer', 'min:0'],
            '*.unit' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * Map a free-form unit value onto the shop's standard units.
     */
    private function normalizeUnit(mixed $value): string
    {
        $unit = trim((string) $value);

        if ($unit === '') {
            return 'Pcs';
        }

        foreach (Unit::UNITS as $standard) {
            if (Str::lower($standard) === Str::lower($unit)) {
                return $standard;
            }
        }

        return $unit;
    }
}
