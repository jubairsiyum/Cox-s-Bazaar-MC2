<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountTransactionController;
use App\Http\Controllers\AccountTransferController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchStockRequestController;
use App\Http\Controllers\BranchTargetController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DayClosingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PlaceholderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseDueController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\SupplierProfileController;
use App\Http\Controllers\TransactionCategoryController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/offline', fn () => view('offline'))->name('offline');

if (file_exists(__DIR__.'/error-preview.php')) {
    require __DIR__.'/error-preview.php';
}

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::middleware('browser_auth')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');
    });

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:6,1');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store')->middleware('throttle:6,1');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::middleware('verified')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/metrics', [DashboardController::class, 'metrics'])
            ->middleware('throttle:60,1')
            ->name('dashboard.metrics');

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
    Route::middleware(['verified', 'permission:products.view|products.manage'])->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
    });

    Route::middleware(['verified', 'permission:products.view|products.manage'])->group(function () {
        Route::get('products/requests', [ProductRequestController::class, 'index'])->name('products.requests.index');
        Route::get('products/requests/create', [ProductRequestController::class, 'create'])->name('products.requests.create');
        Route::post('products/requests', [ProductRequestController::class, 'store'])->name('products.requests.store');
        Route::get('products/requests/{productRequest}', [ProductRequestController::class, 'show'])->name('products.requests.show');
        Route::patch('products/requests/{productRequest}/approve', [ProductRequestController::class, 'approve'])->name('products.requests.approve');
        Route::patch('products/requests/{productRequest}/reject', [ProductRequestController::class, 'reject'])->name('products.requests.reject');
        Route::patch('products/requests/{productRequest}/receive', [ProductRequestController::class, 'receive'])->name('products.requests.receive');
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

    // Profile access is decided by SupplierPolicy (staff with suppliers.view/manage, or the linked supplier login).
    Route::middleware(['verified'])->group(function () {
        Route::get('suppliers/{supplier}/profile', [SupplierProfileController::class, 'profile'])->name('suppliers.profile');
        Route::patch('suppliers/{supplier}/profile/purchase-orders/{purchaseOrder}/accept', [SupplierProfileController::class, 'acceptOrder'])->name('suppliers.profile.accept-order');
        Route::post('suppliers/{supplier}/profile/payment-requests', [SupplierProfileController::class, 'sendPaymentRequest'])->name('suppliers.profile.payment-requests.store');
        Route::patch('suppliers/{supplier}/profile/payment-requests/{paymentRequest}/cancel', [SupplierProfileController::class, 'cancelPaymentRequest'])->name('suppliers.profile.payment-requests.cancel');
    });

    Route::middleware(['verified'])->group(function () {
        Route::get('my-supplier-profile', [SupplierProfileController::class, 'ownProfile'])->name('suppliers.my-profile');
        Route::get('my-supplier-profile/{section}', [SupplierProfileController::class, 'ownSection'])
            ->whereIn('section', ['details', 'dues', 'orders', 'payments', 'returns', 'requests', 'activity'])
            ->name('suppliers.my-profile.section');
        Route::patch('my-supplier-profile/password', [SupplierProfileController::class, 'updateOwnPassword'])->name('suppliers.my-profile.password');
        Route::patch('my-supplier-profile/requests/{paymentRequest}', [SupplierProfileController::class, 'updateOwnPaymentRequest'])
            ->whereNumber('paymentRequest')
            ->name('suppliers.my-profile.requests.update');
        Route::patch('my-supplier-profile/{supplier}', [SupplierProfileController::class, 'updateOwnProfile'])
            ->whereNumber('supplier')
            ->name('suppliers.my-profile.update');
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
    Route::middleware(['verified', 'permission:branches.manage'])->group(function () {
        Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
        Route::get('branches/create', [BranchController::class, 'create'])->name('branches.create');
        Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
        Route::get('branches/{location}/edit', [BranchController::class, 'edit'])->name('branches.edit');
        Route::get('branches/{location}', [BranchController::class, 'show'])->name('branches.show');
        Route::patch('branches/{location}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('branches/{location}', [BranchController::class, 'destroy'])->name('branches.destroy');
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
        Route::get('stock-transfers/requests/create', [BranchStockRequestController::class, 'create'])->name('stock-transfers.requests.create');
        Route::post('stock-transfers/requests', [BranchStockRequestController::class, 'store'])->name('stock-transfers.requests.store');
        Route::get('stock-transfers/pdf-view', [StockTransferController::class, 'pdfView'])->name('stock-transfers.pdf-view');
        Route::get('stock-transfers/print-view', [StockTransferController::class, 'printView'])->name('stock-transfers.print-view');
        Route::get('stock-transfers/export/{format?}', [StockTransferController::class, 'export'])->name('stock-transfers.export');
        Route::get('stock-transfers/{stockTransfer}/edit', [StockTransferController::class, 'edit'])->name('stock-transfers.edit');
        Route::patch('stock-transfers/{stockTransfer}', [StockTransferController::class, 'update'])->name('stock-transfers.update');
        Route::patch('stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');
        Route::patch('stock-transfers/{stockTransfer}/withdraw', [StockTransferController::class, 'withdraw'])->name('stock-transfers.withdraw');
        Route::delete('stock-transfers/{stockTransfer}', [StockTransferController::class, 'destroy'])->name('stock-transfers.destroy');
        Route::get('stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->name('stock-transfers.show');
    });

    Route::middleware(['verified', 'permission:stock-transfers.manage|stock-transfers.approve'])->group(function () {
        Route::patch('stock-transfers/{stockTransfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('stock-transfers.dispatch');
        Route::patch('stock-transfers/{stockTransfer}/approve', [StockTransferController::class, 'approve'])->name('stock-transfers.approve');
        Route::patch('stock-transfers/{stockTransfer}/reject', [StockTransferController::class, 'reject'])->name('stock-transfers.reject');
    });

    /*
    |--------------------------------------------------------------------------
    | Stocks / Inventory overview
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:inventory.view'])->group(function () {
        Route::get('stocks', [StockController::class, 'index'])->name('stocks.index');
        Route::get('stocks/{unit}/movements', [StockController::class, 'movements'])->name('stocks.movements');
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
    | Notifications (Module 12)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified'])->group(function () {
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::patch('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
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
        Route::patch('purchase-invoices/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-invoices.receive');
        Route::delete('purchase-invoices/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('purchase-invoices.destroy');
    });

    // Legacy cleanup only: purchases take effect on creation, so approve/reject
    // just complete documents that still carry the old pending approval state.
    Route::middleware(['verified', 'permission:purchase-invoices.approve'])->group(function () {
        Route::patch('purchase-invoices/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-invoices.approve');
        Route::patch('purchase-invoices/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])->name('purchase-invoices.reject');
    });

    /*
    |--------------------------------------------------------------------------
    | Purchase Dues (Purchasing) — due payment requests on purchase invoices
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:purchase-invoices.view|purchase-invoices.manage|supplier-payments.view|supplier-payments.manage'])->group(function () {
        Route::get('purchase-dues', [PurchaseDueController::class, 'index'])->name('purchase-dues.index');
        Route::post('purchase-dues', [PurchaseDueController::class, 'store'])->name('purchase-dues.store');
        Route::patch('purchase-dues/{purchasePayment}', [PurchaseDueController::class, 'update'])->name('purchase-dues.update');
        Route::delete('purchase-dues/{purchasePayment}', [PurchaseDueController::class, 'destroy'])->name('purchase-dues.destroy');
    });

    // Approvals are gated by the responsible branch manager (policy), not a permission.
    Route::middleware(['verified'])->group(function () {
        Route::patch('purchase-dues/{purchasePayment}/approve', [PurchaseDueController::class, 'approve'])->name('purchase-dues.approve');
        Route::patch('purchase-dues/{purchasePayment}/reject', [PurchaseDueController::class, 'reject'])->name('purchase-dues.reject');
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
        // Legacy cleanup only: returns take effect when submitted, so these just
        // complete documents that still carry the old pending approval state.
        Route::patch('purchase-returns/{purchaseReturn}/approve', [PurchaseReturnController::class, 'approve'])->name('purchase-returns.approve');
        Route::patch('purchase-returns/{purchaseReturn}/reject', [PurchaseReturnController::class, 'reject'])->name('purchase-returns.reject');
        Route::get('purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->name('purchase-returns.show');
    });
});

/*
|--------------------------------------------------------------------------
| Placeholder routes for planned modules (see docs/modules.md)
| Replaced by real routes as each module is implemented.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    $placeholders = [
        'reports.sales' => 'Reports',
    ];

    // Permission guards for placeholders whose module permissions are already seeded.
    $placeholderPermissions = [
        'reports.sales' => 'permission:reports.view|reports.export',
    ];

    foreach ($placeholders as $name => $label) {
        Route::get($name === 'reports.sales' ? 'reports/sales' : str_replace('.index', '', $name), [PlaceholderController::class, '__invoke'])
            ->defaults('module', $label)
            ->middleware($placeholderPermissions[$name] ?? [])
            ->name($name);
    }
});

/*
|--------------------------------------------------------------------------
| Finance (docs/modules.md Module 07 & 10): cash/bank accounts, the merged
| income+expense "Transactions" section, account transfers and supplier
| payments.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    /*
    | Cash accounts
    */
    Route::middleware('permission:finance.view|finance.manage|cash-accounts.manage')->group(function () {
        Route::get('cash-accounts', [AccountController::class, 'index'])->defaults('type', 'cash')->name('cash-accounts.index');
    });

    Route::middleware('permission:finance.manage|cash-accounts.manage')->group(function () {
        Route::get('cash-accounts/create', [AccountController::class, 'create'])->defaults('type', 'cash')->name('cash-accounts.create');
        Route::post('cash-accounts', [AccountController::class, 'store'])->defaults('type', 'cash')->name('cash-accounts.store');
        Route::get('cash-accounts/{account}/edit', [AccountController::class, 'edit'])->defaults('type', 'cash')->name('cash-accounts.edit');
        Route::patch('cash-accounts/{account}', [AccountController::class, 'update'])->defaults('type', 'cash')->name('cash-accounts.update');
        Route::delete('cash-accounts/{account}', [AccountController::class, 'destroy'])->defaults('type', 'cash')->name('cash-accounts.destroy');
    });

    /*
    | Bank accounts
    */
    Route::middleware('permission:finance.view|finance.manage|bank-accounts.manage')->group(function () {
        Route::get('bank-accounts', [AccountController::class, 'index'])->defaults('type', 'bank')->name('bank-accounts.index');
    });

    Route::middleware('permission:finance.manage|bank-accounts.manage')->group(function () {
        Route::get('bank-accounts/create', [AccountController::class, 'create'])->defaults('type', 'bank')->name('bank-accounts.create');
        Route::post('bank-accounts', [AccountController::class, 'store'])->defaults('type', 'bank')->name('bank-accounts.store');
        Route::get('bank-accounts/{account}/edit', [AccountController::class, 'edit'])->defaults('type', 'bank')->name('bank-accounts.edit');
        Route::patch('bank-accounts/{account}', [AccountController::class, 'update'])->defaults('type', 'bank')->name('bank-accounts.update');
        Route::delete('bank-accounts/{account}', [AccountController::class, 'destroy'])->defaults('type', 'bank')->name('bank-accounts.destroy');
    });

    /*
    | Transactions — the merged income & expense section
    */
    Route::middleware('permission:finance.view|finance.manage|income-transactions.manage|expense-transactions.manage')->group(function () {
        Route::get('transactions', [AccountTransactionController::class, 'index'])->name('transactions.index');
    });

    Route::middleware('permission:finance.manage|income-transactions.manage|expense-transactions.manage')->group(function () {
        Route::get('transactions/create', [AccountTransactionController::class, 'create'])->name('transactions.create');
        Route::post('transactions', [AccountTransactionController::class, 'store'])->name('transactions.store');
        Route::get('transactions/{accountTransaction}/edit', [AccountTransactionController::class, 'edit'])->name('transactions.edit');
        Route::patch('transactions/{accountTransaction}', [AccountTransactionController::class, 'update'])->name('transactions.update');
        Route::delete('transactions/{accountTransaction}', [AccountTransactionController::class, 'destroy'])->name('transactions.destroy');
        Route::patch('transactions/{accountTransaction}/approve', [AccountTransactionController::class, 'approve'])->name('transactions.approve');
        Route::patch('transactions/{accountTransaction}/reject', [AccountTransactionController::class, 'reject'])->name('transactions.reject');
    });

    // Legacy URLs from when income and expenses were separate sections.
    Route::middleware('permission:finance.view|finance.manage|income-transactions.manage|expense-transactions.manage')->group(function () {
        Route::redirect('income-transactions', '/transactions', 302)->name('income-transactions.index');
        Route::redirect('expense-transactions', '/transactions', 302)->name('expense-transactions.index');
    });

    /*
    | Transactions reference data — the "Transaction Category" and "Method"
    | master lists that populate the Finance dropdowns
    */
    Route::middleware('permission:finance.view|finance.manage|income-transactions.manage|expense-transactions.manage')->group(function () {
        Route::get('transaction-categories', [TransactionCategoryController::class, 'index'])->name('transaction-categories.index');
        Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
    });

    Route::middleware('permission:finance.manage|income-transactions.manage|expense-transactions.manage')->group(function () {
        Route::get('transaction-categories/create', [TransactionCategoryController::class, 'create'])->name('transaction-categories.create');
        Route::post('transaction-categories', [TransactionCategoryController::class, 'store'])->name('transaction-categories.store');
        Route::get('transaction-categories/{transactionCategory}/edit', [TransactionCategoryController::class, 'edit'])->name('transaction-categories.edit');
        Route::patch('transaction-categories/{transactionCategory}', [TransactionCategoryController::class, 'update'])->name('transaction-categories.update');
        Route::patch('transaction-categories/{transactionCategory}/toggle-status', [TransactionCategoryController::class, 'toggleStatus'])->name('transaction-categories.toggle-status');
        Route::delete('transaction-categories/{transactionCategory}', [TransactionCategoryController::class, 'destroy'])->name('transaction-categories.destroy');

        Route::get('payment-methods/create', [PaymentMethodController::class, 'create'])->name('payment-methods.create');
        Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::get('payment-methods/{paymentMethod}/edit', [PaymentMethodController::class, 'edit'])->name('payment-methods.edit');
        Route::patch('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
        Route::patch('payment-methods/{paymentMethod}/toggle-status', [PaymentMethodController::class, 'toggleStatus'])->name('payment-methods.toggle-status');
        Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
    });

    /*
    | Account transfers
    */
    Route::middleware('permission:finance.view|finance.manage|account-transfers.manage')->group(function () {
        Route::get('account-transfers', [AccountTransferController::class, 'index'])->name('account-transfers.index');
    });

    Route::middleware('permission:finance.manage|account-transfers.manage')->group(function () {
        Route::get('account-transfers/create', [AccountTransferController::class, 'create'])->name('account-transfers.create');
        Route::post('account-transfers', [AccountTransferController::class, 'store'])->name('account-transfers.store');
        Route::get('account-transfers/{accountTransfer}/edit', [AccountTransferController::class, 'edit'])->name('account-transfers.edit');
        Route::patch('account-transfers/{accountTransfer}', [AccountTransferController::class, 'update'])->name('account-transfers.update');
        Route::delete('account-transfers/{accountTransfer}', [AccountTransferController::class, 'destroy'])->name('account-transfers.destroy');
        Route::patch('account-transfers/{accountTransfer}/approve', [AccountTransferController::class, 'approve'])->name('account-transfers.approve');
        Route::patch('account-transfers/{accountTransfer}/reject', [AccountTransferController::class, 'reject'])->name('account-transfers.reject');
    });

    Route::middleware('permission:finance.view|finance.manage|account-transfers.manage')->group(function () {
        Route::get('day-closings', [DayClosingController::class, 'index'])->name('day-closings.index');
        Route::get('day-closings/create', [DayClosingController::class, 'create'])->name('day-closings.create');
        Route::post('day-closings', [DayClosingController::class, 'store'])->name('day-closings.store');
        Route::patch('day-closings/{dayClosing}/reopen', [DayClosingController::class, 'reopen'])->name('day-closings.reopen');
    });

    /*
    | Supplier payments (finance side of purchase_payments)
    */
    Route::middleware('permission:finance.manage|supplier-payments.manage')->group(function () {
        Route::get('supplier-payments/create', [SupplierPaymentController::class, 'create'])->name('supplier-payments.create');
        Route::post('supplier-payments', [SupplierPaymentController::class, 'store'])->name('supplier-payments.store');
        Route::delete('supplier-payments/{purchasePayment}', [SupplierPaymentController::class, 'destroy'])->name('supplier-payments.destroy');
    });

    Route::middleware('permission:finance.view|finance.manage|supplier-payments.view|supplier-payments.manage')->group(function () {
        Route::get('supplier-payments', [SupplierPaymentController::class, 'index'])->name('supplier-payments.index');
        Route::get('supplier-payments/{purchasePayment}', [SupplierPaymentController::class, 'show'])->name('supplier-payments.show');
    });
});

/*
|--------------------------------------------------------------------------
| Sales section (docs/modules.md Modules 09–11): orders, invoices, returns,
| collections, POS and the sales report.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    $salesOrderController = SalesOrderController::class;
    $salesInvoiceController = SalesInvoiceController::class;
    $salesReturnController = SalesReturnController::class;
    $posController = PosController::class;
    $salesReportController = SalesReportController::class;

    /*
    | Sales Orders
    */
    Route::get('sales-orders/search-units', [$salesOrderController, 'searchUnits'])
        ->name('sales-orders.search-units')
        ->middleware('permission:sales-orders.manage|sales-invoices.manage');

    Route::get('sales-orders/search-customers', [$salesOrderController, 'searchCustomers'])
        ->name('sales-orders.search-customers')
        ->middleware('permission:sales-orders.manage|sales-invoices.manage');

    Route::middleware('permission:sales-orders.manage')->group(function () use ($salesOrderController) {
        Route::get('sales-orders', [$salesOrderController, 'index'])->name('sales-orders.index');
        Route::get('sales-orders/create', [$salesOrderController, 'create'])->name('sales-orders.create');
        Route::post('sales-orders', [$salesOrderController, 'store'])->name('sales-orders.store');
        Route::get('sales-orders/{salesOrder}', [$salesOrderController, 'show'])->name('sales-orders.show');
        Route::get('sales-orders/{salesOrder}/edit', [$salesOrderController, 'edit'])->name('sales-orders.edit');
        Route::patch('sales-orders/{salesOrder}', [$salesOrderController, 'update'])->name('sales-orders.update');
        Route::delete('sales-orders/{salesOrder}', [$salesOrderController, 'destroy'])->name('sales-orders.destroy');
        Route::patch('sales-orders/{salesOrder}/confirm', [$salesOrderController, 'confirm'])->name('sales-orders.confirm');
        Route::patch('sales-orders/{salesOrder}/cancel', [$salesOrderController, 'cancel'])->name('sales-orders.cancel');
    });

    /*
    | Sales Invoices — register, draft workflow and printable invoice
    */
    Route::middleware('permission:sales-invoices.view|sales-invoices.manage|sales-invoices.post')->group(function () use ($salesInvoiceController) {
        Route::get('sales-invoices/search-units', [$salesInvoiceController, 'searchUnits'])->name('sales-invoices.search-units');
        Route::get('sales-invoices/export/{format?}', [$salesInvoiceController, 'export'])->name('sales-invoices.export');
    });

    Route::middleware('permission:sales-invoices.manage')->group(function () use ($salesInvoiceController) {
        Route::get('sales-invoices/create', [$salesInvoiceController, 'create'])->name('sales-invoices.create');
        Route::post('sales-invoices', [$salesInvoiceController, 'store'])->name('sales-invoices.store');
        Route::get('sales-invoices/{salesInvoice}/edit', [$salesInvoiceController, 'edit'])->name('sales-invoices.edit');
        Route::patch('sales-invoices/{salesInvoice}', [$salesInvoiceController, 'update'])->name('sales-invoices.update');
        Route::delete('sales-invoices/{salesInvoice}', [$salesInvoiceController, 'destroy'])->name('sales-invoices.destroy');
    });

    Route::middleware('permission:sales-invoices.view|sales-invoices.manage|sales-invoices.post')->group(function () use ($salesInvoiceController) {
        Route::get('sales-invoices', [$salesInvoiceController, 'index'])->name('sales-invoices.index');
        Route::get('sales-invoices/{salesInvoice}', [$salesInvoiceController, 'show'])->name('sales-invoices.show');
        Route::get('sales-invoices/{salesInvoice}/print', [$salesInvoiceController, 'print'])->name('sales-invoices.print');
    });

    Route::middleware('permission:sales-invoices.post')->group(function () use ($salesInvoiceController) {
        Route::patch('sales-invoices/{salesInvoice}/post', [$salesInvoiceController, 'post'])->name('sales-invoices.post');
        Route::patch('sales-invoices/{salesInvoice}/void', [$salesInvoiceController, 'void'])->name('sales-invoices.void');
    });

    /*
    | Sales Returns — "Apply Return" flow with branch-manager approval.
    | show/approve/reject are open to holders of either sales-returns.manage
    | or sales-returns.approve; the policy then narrows approvals to the
    | responsible branch manager (or Super Admin).
    */
    Route::middleware('permission:sales-returns.manage')->group(function () use ($salesReturnController) {
        Route::get('sales-returns/customer-invoices', [$salesReturnController, 'customerInvoices'])->name('sales-returns.customer-invoices');
        Route::get('sales-returns/invoice-details', [$salesReturnController, 'invoiceDetails'])->name('sales-returns.invoice-details');
        Route::get('sales-returns', [$salesReturnController, 'index'])->name('sales-returns.index');
        Route::get('sales-returns/create', [$salesReturnController, 'create'])->name('sales-returns.create');
        Route::post('sales-returns', [$salesReturnController, 'store'])->name('sales-returns.store');
        Route::get('sales-returns/{salesReturn}/edit', [$salesReturnController, 'edit'])->name('sales-returns.edit');
        Route::patch('sales-returns/{salesReturn}', [$salesReturnController, 'update'])->name('sales-returns.update');
        Route::delete('sales-returns/{salesReturn}', [$salesReturnController, 'destroy'])->name('sales-returns.destroy');
    });

    Route::middleware('permission:sales-returns.manage|sales-returns.approve')->group(function () use ($salesReturnController) {
        Route::get('sales-returns/{salesReturn}', [$salesReturnController, 'show'])->name('sales-returns.show');
        Route::patch('sales-returns/{salesReturn}/approve', [$salesReturnController, 'approve'])->name('sales-returns.approve');
        Route::patch('sales-returns/{salesReturn}/reject', [$salesReturnController, 'reject'])->name('sales-returns.reject');
    });

    /*
    | Collections — every distributor (sales) invoice in one list, generated
    | from a sales order or rung up on the POS alike. The customer-collections
    | payment screens are retired "for now" and all of their routes are gone.
    */
    Route::middleware('permission:sales-invoices.view|sales-invoices.manage|customer-collections.manage')->group(function () use ($salesInvoiceController) {
        Route::get('collections', [$salesInvoiceController, 'distributorIndex'])->name('collections.index');
    });

    /*
    | POS — quick sale terminal
    */
    Route::middleware('permission:pos.access')->group(function () use ($posController) {
        Route::get('pos', [$posController, 'index'])->name('pos.index');
        Route::get('pos/search-units', [$posController, 'searchUnits'])->name('pos.search-units');
        Route::post('pos', [$posController, 'store'])->name('pos.store');
    });

    /*
    | Sales report
    */
    Route::get('reports/sales', [$salesReportController, 'sales'])
        ->middleware('permission:reports.view|reports.export')
        ->name('reports.sales');

    /*
    | Monthly Sales Targets — Super Admin sets per branch, branch managers track pacing & DoD
    */
    Route::get('branch-targets', [BranchTargetController::class, 'index'])
        ->middleware(['verified', 'permission:sales-invoices.view|finance.view'])
        ->name('branch-targets.index');
    Route::post('branch-targets', [BranchTargetController::class, 'store'])
        ->middleware('verified')
        ->name('branch-targets.store');
    Route::delete('branch-targets/{branchTarget}', [BranchTargetController::class, 'destroy'])
        ->middleware('verified')
        ->name('branch-targets.destroy');
});
