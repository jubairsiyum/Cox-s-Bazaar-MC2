<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Customer;
use App\Services\NumberingService;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CustomerImport implements SkipsEmptyRows, ToModel, WithHeadingRow, WithValidation
{
    private int $importedCount = 0;

    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): ?Customer
    {
        $name = $row['customer_name'] ?? $row['name'] ?? null;

        if (blank($name)) {
            return null;
        }

        $mobile = $row['mobile_no'] ?? $row['mobile'] ?? $row['mobile_number'] ?? null;
        $phone = $row['tel_no'] ?? $row['tel'] ?? $row['phone'] ?? $row['contact_no'] ?? null;

        $this->importedCount++;

        return new Customer([
            'customer_id' => NumberingService::nextSequential('customer_prefix', 'CU', 4),
            'name' => trim((string) $name),
            'mobile' => $mobile !== null ? trim((string) $mobile) : null,
            'tel_no' => $phone !== null ? trim((string) $phone) : null,
            'email' => isset($row['email']) ? trim((string) $row['email']) : null,
            'country' => $row['country_name'] ?? $row['country'] ?? 'Bangladesh',
            'state' => $row['state_name'] ?? $row['state'] ?? null,
            'city' => isset($row['city']) ? trim((string) $row['city']) : null,
            'postcode' => isset($row['postcode']) ? trim((string) $row['postcode']) : null,
            'address' => isset($row['address']) ? trim((string) $row['address']) : null,
            'note' => isset($row['note']) ? trim((string) $row['note']) : null,
            'status' => 'active',
            'created_by' => Auth::id(),
        ]);
    }

    public function rules(): array
    {
        return [
            '*.customer_name' => ['required_without:*.name'],
            '*.name' => ['required_without:*.customer_name'],
            '*.mobile_no' => ['required_without_all:*.mobile,*.mobile_number'],
            '*.mobile' => ['required_without_all:*.mobile_no,*.mobile_number'],
            '*.email' => ['nullable', 'email'],
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
