<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Unit;
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
        $productName = $row['product_name'] ?? $row['product'] ?? null;

        if (blank($productName)) {
            return null;
        }

        $this->importedCount++;

        return new Unit([
            'product_name' => trim((string) $productName),
            'quantity' => (int) ($row['quantity'] ?? 0),
            'unit' => $this->normalizeUnit($row['unit'] ?? null),
            'note' => isset($row['note']) ? trim((string) $row['note']) : null,
            'created_by' => Auth::id(),
        ]);
    }

    public function rules(): array
    {
        return [
            '*.product_name' => ['required_without:*.product'],
            '*.product' => ['required_without:*.product_name'],
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
