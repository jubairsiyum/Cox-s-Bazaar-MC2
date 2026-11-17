<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Supplier;
use App\Services\DuplicateCheckService;
use App\Services\NumberingService;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SupplierImport implements SkipsEmptyRows, ToModel, WithHeadingRow, WithValidation
{
    private int $importedCount = 0;

    private int $skippedCount = 0;

    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): ?Supplier
    {
        $name = $row['supplier_name'] ?? $row['name'] ?? null;

        if (blank($name)) {
            return null;
        }

        $mobile = $row['mobile_no'] ?? $row['mobile'] ?? $row['mobile_number'] ?? null;
        $phone = $row['tel_no'] ?? $row['tel'] ?? $row['phone'] ?? $row['contact_no'] ?? null;

        $attributes = [
            'name' => trim((string) $name),
            'mobile' => $mobile !== null ? trim((string) $mobile) : null,
            'email' => isset($row['email']) ? trim((string) $row['email']) : null,
            'tel_no' => $phone !== null ? trim((string) $phone) : null,
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
        ];

        if (DuplicateCheckService::liveConflicts(Supplier::class, $attributes) !== []) {
            $this->skippedCount++;

            return null;
        }

        $reusable = DuplicateCheckService::reusableRecord(Supplier::class, $attributes);

        if ($reusable instanceof Supplier) {
            DuplicateCheckService::purgeTrashConflicts(Supplier::class, $attributes, $reusable->id);

            $reusable->fill($attributes);
            $reusable->deleted_at = null;

            $this->importedCount++;

            return $reusable;
        }

        $this->importedCount++;

        return new Supplier($attributes + [
            'supplier_id' => NumberingService::nextSequential('supplier_prefix', 'SP', 4),
        ]);
    }

    public function rules(): array
    {
        return [
            '*.supplier_name' => ['required_without:*.name'],
            '*.name' => ['required_without:*.supplier_name'],
            '*.mobile_no' => ['required_without_all:*.mobile,*.mobile_number'],
            '*.mobile' => ['required_without_all:*.mobile_no,*.mobile_number'],
            '*.email' => ['nullable', 'email'],
            '*.opening_balance' => ['nullable', 'numeric'],
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }
}
