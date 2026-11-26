<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;

/**
 * Resolves the customer fields of a sales order form to a stored customer
 * profile, or creates one, following the customer identity rules (name,
 * phone and email are unique identities — see the customer/supplier identity
 * migration and {@see DuplicateCheckService}).
 *
 * Resolution order:
 * 1. An explicitly selected customer id wins; an edited phone number or
 *    email is saved back to that customer's profile for future tracking.
 * 2. A typed name matching an existing customer resolves to that customer —
 *    names are unique identities, so typing one never duplicates it.
 * 3. A soft-deleted customer with the same name/phone/email is restored and
 *    updated instead of leaving a redundant row behind (the same rule
 *    CustomerController::store applies).
 * 4. Otherwise a new customer is created with the typed name + contact
 *    details.
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
    public static function identityConflicts(?int $customerId, ?string $name, ?string $phone, ?string $email = null): array
    {
        $ignoreId = $customerId ?? self::matchByName($name)?->id;

        $conflicts = DuplicateCheckService::liveConflicts(
            Customer::class,
            ['name' => $name, 'mobile' => $phone, 'email' => $email],
            $ignoreId
        );

        $fields = [
            'name' => 'customer_name',
            'mobile' => 'mobile_no',
            'email' => 'customer_email',
        ];

        $mapped = [];

        foreach ($conflicts as $column => $record) {
            $mapped[$fields[$column] ?? $column] = $record;
        }

        return $mapped;
    }

    /**
     * Resolve the submitted customer fields to a stored customer profile.
     *
     * @param  string  $source  Human-readable origin used in audit messages.
     */
    public static function resolveOrCreate(?int $customerId, ?string $name, ?string $phone, ?string $email = null, ?int $createdBy = null, string $source = 'a sales order'): Customer
    {
        $phone = DuplicateCheckService::normalize('mobile', $phone);
        $email = DuplicateCheckService::normalize('email', $email);

        if ($customerId !== null) {
            $customer = Customer::query()->findOrFail($customerId);
            self::syncContact($customer, $phone, $email, $source);

            return $customer;
        }

        $name = DuplicateCheckService::normalize('name', $name);
        $attributes = ['name' => $name, 'mobile' => $phone, 'email' => $email];

        $matched = self::matchByName($name);

        if ($matched instanceof Customer) {
            self::syncContact($matched, $phone, $email, $source);

            return $matched;
        }

        $reusable = DuplicateCheckService::reusableRecord(Customer::class, $attributes);

        if ($reusable instanceof Customer) {
            DuplicateCheckService::purgeTrashConflicts(Customer::class, $attributes, $reusable->id);

            $reusable->fill($attributes);
            $reusable->restore();

            AuditService::created($reusable, "Customer '{$reusable->name}' ({$reusable->customer_id}) restored from {$source}.");

            return $reusable;
        }

        $customer = Customer::create([
            'name' => $name,
            'mobile' => $phone,
            'email' => $email,
            'created_by' => $createdBy,
        ]);

        AuditService::created($customer, "Customer '{$customer->name}' ({$customer->customer_id}) created from {$source}.");

        return $customer;
    }

    /**
     * Persist an edited mobile number or email back to the customer's profile.
     */
    private static function syncContact(Customer $customer, ?string $phone, ?string $email, string $source = 'a sales order'): void
    {
        $labels = ['mobile' => 'mobile number', 'email' => 'email'];
        $changes = [];

        if ($phone !== null && $phone !== $customer->mobile) {
            $changes['mobile'] = $phone;
        }

        if ($email !== $customer->email) {
            $changes['email'] = $email;
        }

        if ($changes === []) {
            return;
        }

        $original = $customer->getOriginal();

        $customer->update($changes);

        $updated = implode(' and ', array_map(fn (string $column): string => $labels[$column], array_keys($changes)));

        AuditService::updated($customer, $original, "Customer '{$customer->name}' ({$customer->customer_id}) {$updated} updated from {$source}.");
    }
}
