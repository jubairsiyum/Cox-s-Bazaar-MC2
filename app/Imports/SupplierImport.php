<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Supplier;
use App\Services\NumberingService;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SupplierImport implements SkipsEmptyRows, ToModel, WithHeadingRow, WithValidation
{
    private int $importedCount = 0;

    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): ?Supplier
    {
        $name = $row['supplier_name'] ?? $row['name'] ?? null;

        if (blank($name)) {
            return null;
        }

        $this->importedCount++;

        return new Supplier([
            'supplier_id' => NumberingService::nextSequential('supplier_prefix', 'SP', 4),
            'name' => trim((string) $name),
            'mobile' => isset($row['mobile']) ? trim((string) $row['mobile']) : null,
            'email' => isset($row['email']) ? trim((string) $row['email']) : null,
            'phone' => isset($row['phone']) ? trim((string) $row['phone']) : null,
            'gst_number' => isset($row['gst_number']) ? trim((string) $row['gst_number']) : null,
            'tax_number' => isset($row['tax_number']) ? trim((string) $row['tax_number']) : null,
            'country' => $row['country_name'] ?? $row['country'] ?? 'Bangladesh',
            'state' => $row['state_name'] ?? $row['state'] ?? null,
            'city' => isset($row['city']) ? trim((string) $row['city']) : null,
            'postcode' => isset($row['postcode']) ? trim((string) $row['postcode']) : null,
            'address' => isset($row['address']) ? trim((string) $row['address']) : null,
            'opening_balance' => (float) ($row['opening_balance'] ?? 0),
            'purchase_due' => (float) ($row['opening_balance'] ?? 0),
            'purchase_return_due' => 0.00,
            'status' => 'active',
            'created_by' => Auth::id(),
        ]);
    }

    public function rules(): array
    {
        return [
            '*.supplier_name' => ['required_without:*.name'],
            '*.name' => ['required_without:*.supplier_name'],
            '*.email' => ['nullable', 'email'],
            '*.opening_balance' => ['nullable', 'numeric'],
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
