<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PlaceholderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/offline', fn () => view('offline'))->name('offline');

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::middleware(['security_headers', 'browser_auth'])->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');
    });

    Route::middleware('security_headers')->group(function () {
        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:6,1');

        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store')->middleware('throttle:6,1');
    });
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('verify-email', [EmailVerificationController::class, 'notice'])->middleware('security_headers')->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::middleware('verified')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('password', [ProfileController::class, 'updatePassword'])->name('password.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Super Admin portal — User & Role management
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:users.view'])->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create')->middleware('permission:users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store')->middleware('permission:users.create');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit')->middleware('permission:users.update');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('permission:users.update');
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status')->middleware('permission:users.deactivate');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('permission:users.deactivate');
    });

    Route::middleware(['verified', 'permission:roles.view'])->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    });

    Route::middleware(['verified', 'permission:roles.manage'])->group(function () {
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index')->middleware('permission:audit-logs.view');

    Route::middleware(['verified', 'permission:settings.manage'])->group(function () {
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Unit / Quantity Management (Master Data)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:units.view'])->group(function () {
        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::get('units/pdf-view', [UnitController::class, 'pdfView'])->name('units.pdf-view');
        Route::get('units/print-view', [UnitController::class, 'printView'])->name('units.print-view');
        Route::get('units/export/{format?}', [UnitController::class, 'export'])->name('units.export');
        Route::get('units/import/template', [UnitController::class, 'downloadTemplate'])->name('units.import-template');
    });

    Route::middleware(['verified', 'permission:units.manage'])->group(function () {
        Route::post('units', [UnitController::class, 'store'])->name('units.store');
        Route::get('units/import', [UnitController::class, 'importView'])->name('units.import');
        Route::post('units/import', [UnitController::class, 'import'])->name('units.import.store');
        Route::patch('units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Brand Management (Master Data)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:brands.view'])->group(function () {
        Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
        Route::get('brands/pdf-view', [BrandController::class, 'pdfView'])->name('brands.pdf-view');
        Route::get('brands/print-view', [BrandController::class, 'printView'])->name('brands.print-view');
        Route::get('brands/export/{format?}', [BrandController::class, 'export'])->name('brands.export');
    });

    Route::middleware(['verified', 'permission:brands.manage'])->group(function () {
        Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
        Route::patch('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Category Management (Master Data)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:categories.view'])->group(function () {
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/pdf-view', [CategoryController::class, 'pdfView'])->name('categories.pdf-view');
        Route::get('categories/print-view', [CategoryController::class, 'printView'])->name('categories.print-view');
        Route::get('categories/export/{format?}', [CategoryController::class, 'export'])->name('categories.export');
    });

    Route::middleware(['verified', 'permission:categories.manage'])->group(function () {
        Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::patch('categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle-status');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Product Management (Master Data)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:products.view'])->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
    });

    Route::middleware(['verified', 'permission:products.manage'])->group(function () {
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle-status');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Supplier Management
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:suppliers.view'])->group(function () {
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('suppliers/pdf-view', [SupplierController::class, 'pdfView'])->name('suppliers.pdf-view');
        Route::get('suppliers/print-view', [SupplierController::class, 'printView'])->name('suppliers.print-view');
        Route::get('suppliers/export/{format?}', [SupplierController::class, 'export'])->name('suppliers.export');
        Route::get('suppliers/import/template', [SupplierController::class, 'downloadTemplate'])->name('suppliers.import-template');
    });

    Route::middleware(['verified', 'permission:suppliers.manage'])->group(function () {
        Route::get('suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('suppliers/import', [SupplierController::class, 'importView'])->name('suppliers.import');
        Route::post('suppliers/import', [SupplierController::class, 'import'])->name('suppliers.import.store');
        Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::patch('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::patch('suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('suppliers.toggle-status');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
        Route::post('suppliers/{supplier}/pay-due', [SupplierController::class, 'payDue'])->name('suppliers.pay-due');
        Route::post('suppliers/{supplier}/pay-return-due', [SupplierController::class, 'payReturnDue'])->name('suppliers.pay-return-due');
        Route::delete('suppliers/payments/{payment}', [SupplierController::class, 'destroyPayment'])->name('suppliers.payments.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Customer Management
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:customers.view'])->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/pdf-view', [CustomerController::class, 'pdfView'])->name('customers.pdf-view');
        Route::get('customers/print-view', [CustomerController::class, 'printView'])->name('customers.print-view');
        Route::get('customers/export/{format?}', [CustomerController::class, 'export'])->name('customers.export');
        Route::get('customers/import/template', [CustomerController::class, 'downloadTemplate'])->name('customers.import-template');
    });

    Route::middleware(['verified', 'permission:customers.manage'])->group(function () {
        Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/import', [CustomerController::class, 'importView'])->name('customers.import');
        Route::post('customers/import', [CustomerController::class, 'import'])->name('customers.import.store');
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::patch('customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::post('customers/custom-fields', [CustomerController::class, 'storeCustomField'])->name('customers.custom-fields.store');
        Route::delete('customers/custom-fields/{customField}', [CustomerController::class, 'destroyCustomField'])->name('customers.custom-fields.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Location / Warehouse Management (Inventory)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:warehouses.manage'])->group(function () {
        Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::get('warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
        Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::get('warehouses/{location}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
        Route::patch('warehouses/{location}', [WarehouseController::class, 'update'])->name('warehouses.update');
        Route::delete('warehouses/{location}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Stock Transfers (Inventory)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:stock-transfers.manage'])->group(function () {
        Route::get('stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
        Route::get('stock-transfers/search-units', [StockTransferController::class, 'searchUnits'])->name('stock-transfers.search-units');
        Route::get('stock-transfers/create', [StockTransferController::class, 'create'])->name('stock-transfers.create');
        Route::post('stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
        Route::get('stock-transfers/pdf-view', [StockTransferController::class, 'pdfView'])->name('stock-transfers.pdf-view');
        Route::get('stock-transfers/print-view', [StockTransferController::class, 'printView'])->name('stock-transfers.print-view');
        Route::get('stock-transfers/export/{format?}', [StockTransferController::class, 'export'])->name('stock-transfers.export');
        Route::get('stock-transfers/{stockTransfer}/edit', [StockTransferController::class, 'edit'])->name('stock-transfers.edit');
        Route::patch('stock-transfers/{stockTransfer}', [StockTransferController::class, 'update'])->name('stock-transfers.update');
        Route::delete('stock-transfers/{stockTransfer}', [StockTransferController::class, 'destroy'])->name('stock-transfers.destroy');
        Route::get('stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->name('stock-transfers.show');
    });

    Route::middleware(['verified', 'permission:stock-transfers.manage|stock-transfers.approve'])->group(function () {
        Route::patch('stock-transfers/{stockTransfer}/approve', [StockTransferController::class, 'approve'])->name('stock-transfers.approve');
        Route::patch('stock-transfers/{stockTransfer}/reject', [StockTransferController::class, 'reject'])->name('stock-transfers.reject');
    });

    /*
    |--------------------------------------------------------------------------
    | Stock Levels / Inventory overview
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:inventory.view'])->group(function () {
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/{unit}/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
    });

    /*
    |--------------------------------------------------------------------------
    | Stock Adjustments (Inventory)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:stock-adjustments.manage'])->group(function () {
        Route::get('stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
        Route::get('stock-adjustments/search-units', [StockAdjustmentController::class, 'searchUnits'])->name('stock-adjustments.search-units');
        Route::get('stock-adjustments/create', [StockAdjustmentController::class, 'create'])->name('stock-adjustments.create');
        Route::post('stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
        Route::get('stock-adjustments/pdf-view', [StockAdjustmentController::class, 'pdfView'])->name('stock-adjustments.pdf-view');
        Route::get('stock-adjustments/print-view', [StockAdjustmentController::class, 'printView'])->name('stock-adjustments.print-view');
        Route::get('stock-adjustments/export/{format?}', [StockAdjustmentController::class, 'export'])->name('stock-adjustments.export');
        Route::get('stock-adjustments/{stockAdjustment}/edit', [StockAdjustmentController::class, 'edit'])->name('stock-adjustments.edit');
        Route::patch('stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'update'])->name('stock-adjustments.update');
        Route::delete('stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'destroy'])->name('stock-adjustments.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Purchase Orders (Purchasing) — the "Add" form section
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:purchase-orders.manage'])->group(function () {
        Route::get('purchase-orders', fn () => redirect()->route('purchase-orders.create'))->name('purchase-orders.index');
        Route::get('purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
    });

    Route::get('purchase-orders/search-units', [PurchaseOrderController::class, 'searchUnits'])
        ->name('purchase-orders.search-units')
        ->middleware(['verified', 'permission:purchase-orders.manage|purchase-invoices.manage']);

    /*
    |--------------------------------------------------------------------------
    | Purchase Invoices (Purchasing) — the register of purchase records
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:purchase-invoices.view|purchase-invoices.manage|purchase-invoices.post'])->group(function () {
        Route::get('purchase-invoices', [PurchaseOrderController::class, 'index'])->name('purchase-invoices.index');
        Route::get('purchase-invoices/pdf-view', [PurchaseOrderController::class, 'pdfView'])->name('purchase-invoices.pdf-view');
        Route::get('purchase-invoices/print-view', [PurchaseOrderController::class, 'printView'])->name('purchase-invoices.print-view');
        Route::get('purchase-invoices/export/{format?}', [PurchaseOrderController::class, 'export'])->name('purchase-invoices.export');
        Route::get('purchase-invoices/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-invoices.show');
    });

    Route::middleware(['verified', 'permission:purchase-invoices.manage'])->group(function () {
        Route::get('purchase-invoices/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-invoices.edit');
        Route::patch('purchase-invoices/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-invoices.update');
        Route::delete('purchase-invoices/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('purchase-invoices.destroy');
    });

    Route::middleware(['verified', 'permission:purchase-invoices.approve'])->group(function () {
        Route::patch('purchase-invoices/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-invoices.approve');
        Route::patch('purchase-invoices/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])->name('purchase-invoices.reject');
    });

    /*
    |--------------------------------------------------------------------------
    | Purchase Returns (Purchasing)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:purchase-returns.manage'])->group(function () {
        Route::get('purchase-returns', [PurchaseReturnController::class, 'index'])->name('purchase-returns.index');
        Route::get('purchase-returns/supplier-purchases', [PurchaseReturnController::class, 'supplierPurchases'])->name('purchase-returns.supplier-purchases');
        Route::get('purchase-returns/purchase-details', [PurchaseReturnController::class, 'purchaseDetails'])->name('purchase-returns.purchase-details');
        Route::get('purchase-returns/create', [PurchaseReturnController::class, 'create'])->name('purchase-returns.create');
        Route::post('purchase-returns', [PurchaseReturnController::class, 'store'])->name('purchase-returns.store');
        Route::get('purchase-returns/pdf-view', [PurchaseReturnController::class, 'pdfView'])->name('purchase-returns.pdf-view');
        Route::get('purchase-returns/print-view', [PurchaseReturnController::class, 'printView'])->name('purchase-returns.print-view');
        Route::get('purchase-returns/export/{format?}', [PurchaseReturnController::class, 'export'])->name('purchase-returns.export');
        Route::get('purchase-returns/{purchaseReturn}/edit', [PurchaseReturnController::class, 'edit'])->name('purchase-returns.edit');
        Route::patch('purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'update'])->name('purchase-returns.update');
        Route::delete('purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'destroy'])->name('purchase-returns.destroy');
        Route::patch('purchase-returns/{purchaseReturn}/approve', [PurchaseReturnController::class, 'approve'])->name('purchase-returns.approve');
        Route::patch('purchase-returns/{purchaseReturn}/reject', [PurchaseReturnController::class, 'reject'])->name('purchase-returns.reject');
        Route::get('purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->name('purchase-returns.show');
    });

    /*
    |--------------------------------------------------------------------------
    | TailAdmin Showcase & Demos
    |--------------------------------------------------------------------------
    */
    Route::get('ecommerce', fn () => view('pages.dashboard.ecommerce', ['title' => 'E-commerce Dashboard']))->name('ecommerce');
    Route::get('calendar', fn () => view('pages.calendar', ['title' => 'Calendar']))->name('calendar');
    Route::get('form-elements', fn () => view('pages.forms.form-elements', ['title' => 'Form Elements']))->name('form-elements');
    Route::get('basic-tables', fn () => view('pages.tables.basic-tables', ['title' => 'Basic Tables']))->name('basic-tables');
    Route::get('blank', fn () => view('pages.pages.blank', ['title' => 'Blank']))->name('blank');
    Route::get('error-404', fn () => view('pages.errors.error-404', ['title' => 'Error 404']))->name('error-404');
    Route::get('line-chart', fn () => view('pages.chart.line-chart', ['title' => 'Line Chart']))->name('line-chart');
    Route::get('bar-chart', fn () => view('pages.chart.bar-chart', ['title' => 'Bar Chart']))->name('bar-chart');
    Route::get('alerts', fn () => view('pages.ui-elements.alerts', ['title' => 'Alerts']))->name('alerts');
    Route::get('avatars', fn () => view('pages.ui-elements.avatars', ['title' => 'Avatars']))->name('avatars');
    Route::get('badge', fn () => view('pages.ui-elements.badge', ['title' => 'Badges']))->name('badge');
    Route::get('buttons', fn () => view('pages.ui-elements.buttons', ['title' => 'Buttons']))->name('buttons');
    Route::get('images', fn () => view('pages.ui-elements.images', ['title' => 'Images']))->name('images');
    Route::get('videos', fn () => view('pages.ui-elements.videos', ['title' => 'Videos']))->name('videos');
});

/*
|--------------------------------------------------------------------------
| Placeholder routes for planned modules (see docs/modules.md)
| Replaced by real routes as each module is implemented.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    $placeholders = [
        'sales-orders.index' => 'Sales Orders',
        'sales-invoices.index' => 'Sales Invoices',
        'sales-returns.index' => 'Sales Returns',
        'customer-collections.index' => 'Collections',
        'pos.index' => 'POS Terminal',
        'cash-accounts.index' => 'Cash Accounts',
        'bank-accounts.index' => 'Bank Accounts',
        'income-transactions.index' => 'Income',
        'expense-transactions.index' => 'Expenses',
        'account-transfers.index' => 'Transfers',
        'supplier-payments.index' => 'Supplier Payments',
        'reports.sales' => 'Reports',
    ];

    // Permission guards for placeholders whose module permissions are already seeded.
    $placeholderPermissions = [
        'sales-orders.index' => 'permission:sales-orders.manage',
        'sales-invoices.index' => 'permission:sales-invoices.view|sales-invoices.manage|sales-invoices.post',
        'sales-returns.index' => 'permission:sales-returns.manage',
        'customer-collections.index' => 'permission:customer-collections.manage',
        'pos.index' => 'permission:pos.access',
        'cash-accounts.index' => 'permission:finance.view|finance.manage|cash-accounts.manage',
        'bank-accounts.index' => 'permission:finance.view|finance.manage|bank-accounts.manage',
        'income-transactions.index' => 'permission:finance.view|finance.manage|income-transactions.manage',
        'expense-transactions.index' => 'permission:finance.view|finance.manage|expense-transactions.manage',
        'account-transfers.index' => 'permission:finance.view|finance.manage|account-transfers.manage',
        'supplier-payments.index' => 'permission:finance.view|finance.manage|supplier-payments.view|supplier-payments.manage',
        'reports.sales' => 'permission:reports.view|reports.export',
    ];

    foreach ($placeholders as $name => $label) {
        Route::get($name === 'reports.sales' ? 'reports/sales' : str_replace('.index', '', $name), [PlaceholderController::class, '__invoke'])
            ->defaults('module', $label)
            ->middleware($placeholderPermissions[$name] ?? [])
            ->name($name);
    }
});
