<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Define all permissions ─────────────────────────────────
        $permissions = [
            // Users
            'users.view', 'users.create', 'users.update', 'users.deactivate',
            // Roles
            'roles.view', 'roles.manage',
            // Products
            'brands.manage', 'categories.manage', 'units.manage', 'products.manage',
            'products.view',
            // Suppliers
            'suppliers.manage', 'suppliers.view',
            // Customers
            'customers.manage', 'customers.view',
            // Warehouses & Inventory
            'warehouses.manage', 'inventory.view',
            'stock-adjustments.manage', 'stock-transfers.manage',
            // Purchasing
            'purchase-orders.manage', 'purchase-invoices.manage',
            'purchase-invoices.post', 'purchase-invoices.view',
            'purchase-returns.manage', 'supplier-payments.manage',
            // Sales
            'sales-orders.manage', 'sales-invoices.manage',
            'sales-invoices.post', 'sales-invoices.view',
            'sales-returns.manage', 'customer-collections.manage',
            // POS
            'pos.access',
            // Finance
            'finance.manage', 'finance.view',
            'cash-accounts.manage', 'bank-accounts.manage',
            'income-transactions.manage', 'expense-transactions.manage',
            'account-transfers.manage', 'supplier-payments.view',
            // Reports
            'reports.view', 'reports.export',
            // Settings & Audit
            'settings.manage', 'audit-logs.view',
            // Dashboard
            'dashboard.view',
            // Manage users permission
            'manage-users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ── Super Admin: has all permissions via Gate bypass ──────
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // ── Manager ───────────────────────────────────────────────
        $manager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $manager->syncPermissions([
            'dashboard.view',
            'users.view', 'users.create', 'users.update',
            'manage-users',
            'brands.manage', 'categories.manage', 'units.manage', 'products.manage',
            'suppliers.manage', 'suppliers.view',
            'customers.manage', 'customers.view',
            'warehouses.manage', 'inventory.view',
            'stock-adjustments.manage', 'stock-transfers.manage',
            'purchase-orders.manage', 'purchase-invoices.manage',
            'purchase-invoices.post', 'purchase-invoices.view',
            'purchase-returns.manage', 'supplier-payments.manage',
            'sales-orders.manage', 'sales-invoices.manage',
            'sales-invoices.post', 'sales-invoices.view',
            'sales-returns.manage', 'customer-collections.manage',
            'pos.access',
            'finance.view',
            'reports.view', 'reports.export',
            'audit-logs.view',
        ]);

        // ── Accountant ────────────────────────────────────────────
        $accountant = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions([
            'dashboard.view',
            'products.view', 'suppliers.view', 'customers.view',
            'inventory.view',
            'purchase-invoices.view', 'sales-invoices.view',
            'supplier-payments.view',
            'finance.manage', 'finance.view',
            'cash-accounts.manage', 'bank-accounts.manage',
            'income-transactions.manage', 'expense-transactions.manage',
            'account-transfers.manage',
            'customer-collections.manage', 'supplier-payments.manage',
            'reports.view', 'reports.export',
        ]);

        // ── Sales Staff ───────────────────────────────────────────
        $salesStaff = Role::firstOrCreate(['name' => 'Sales Staff', 'guard_name' => 'web']);
        $salesStaff->syncPermissions([
            'dashboard.view',
            'products.view', 'customers.view', 'inventory.view',
            'sales-orders.manage', 'sales-invoices.manage',
            'sales-invoices.post', 'sales-invoices.view',
            'customer-collections.manage',
            'pos.access',
            'reports.view',
        ]);

        // ── Storekeeper ───────────────────────────────────────────
        $storekeeper = Role::firstOrCreate(['name' => 'Storekeeper', 'guard_name' => 'web']);
        $storekeeper->syncPermissions([
            'dashboard.view',
            'products.manage', 'products.view',
            'brands.manage', 'categories.manage', 'units.manage',
            'warehouses.manage', 'inventory.view',
            'stock-adjustments.manage', 'stock-transfers.manage',
            'purchase-invoices.view', 'purchase-invoices.post',
            'purchase-returns.manage',
        ]);

        $this->command->info('Roles and permissions seeded.');
    }
}
