<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;

/**
 * Resolves the customer fields of a sales order form to a stored customer
 * profile, or creates one, following the customer identity rules (name and
 * phone are unique identities — see the customer/supplier identity migration
 * and {@see DuplicateCheckService}).
 *
 * Resolution order:
 * 1. An explicitly selected customer id wins; an edited phone number is
 *    saved back to that customer's profile for future tracking.
 * 2. A typed name matching an existing customer resolves to that customer —
 *    names are unique identities, so typing one never duplicates it.
 * 3. A soft-deleted customer with the same name/phone is restored and
 *    updated instead of leaving a redundant row behind (the same rule
 *    CustomerController::store applies).
 * 4. Otherwise a new customer is created with the typed name + phone.
 */
class CustomerResolver
{
    /**
     * The live customer a typed name resolves to, if any.
     */
    public static function matchByName(?string $name): ?Customer
    {
        $normalized = DuplicateCheckService::normalize('name', $name);

        if ($normalized === null) {
            return null;
        }

        $match = DuplicateCheckService::liveConflicts(Customer::class, ['name' => $normalized]);

        return $match['name'] ?? null;
    }

    /**
     * Identity conflicts of the submitted customer fields, keyed by the form
     * field they belong to. Conflicts with the customer the submission
     * resolves to are ignored — that profile is being reused or updated, not
     * duplicated.
     *
     * @return array<string, Customer> form field => conflicting customer
     */
    public static function identityConflicts(?int $customerId, ?string $name, ?string $phone): array
    {
        $ignoreId = $customerId ?? self::matchByName($name)?->id;

        $conflicts = DuplicateCheckService::liveConflicts(
            Customer::class,
            ['name' => $name, 'tel_no' => $phone],
            $ignoreId
        );

        $fields = [];

        foreach ($conflicts as $column => $record) {
            $fields[$column === 'name' ? 'customer_name' : 'customer_phone'] = $record;
        }

        return $fields;
    }

    /**
     * Resolve the submitted customer fields to a stored customer profile.
     */
    public static function resolveOrCreate(?int $customerId, ?string $name, ?string $phone, ?int $createdBy = null): Customer
    {
        $phone = DuplicateCheckService::normalize('tel_no', $phone);

        if ($customerId !== null) {
            $customer = Customer::query()->findOrFail($customerId);
            self::syncPhone($customer, $phone);

            return $customer;
        }

        $name = DuplicateCheckService::normalize('name', $name);
        $attributes = ['name' => $name, 'tel_no' => $phone];

        $matched = self::matchByName($name);

        if ($matched instanceof Customer) {
            self::syncPhone($matched, $phone);

            return $matched;
        }

        $reusable = DuplicateCheckService::reusableRecord(Customer::class, $attributes);

        if ($reusable instanceof Customer) {
            DuplicateCheckService::purgeTrashConflicts(Customer::class, $attributes, $reusable->id);

            $reusable->fill($attributes);
            $reusable->restore();

            AuditService::created($reusable, "Customer '{$reusable->name}' ({$reusable->customer_id}) restored from a sales order.");

            return $reusable;
        }

        $customer = Customer::create([
            'name' => $name,
            'tel_no' => $phone,
            'created_by' => $createdBy,
        ]);

        AuditService::created($customer, "Customer '{$customer->name}' ({$customer->customer_id}) created from a sales order.");

        return $customer;
    }

    /**
     * Persist an edited phone number back to the customer's profile.
     */
    private static function syncPhone(Customer $customer, ?string $phone): void
    {
        if ($phone === $customer->tel_no) {
            return;
        }

        $original = $customer->getOriginal();

        $customer->update(['tel_no' => $phone]);

        AuditService::updated($customer, $original, "Customer '{$customer->name}' ({$customer->customer_id}) phone updated from a sales order.");
    }
}
