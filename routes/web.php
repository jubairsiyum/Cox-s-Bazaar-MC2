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
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlaceholderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');

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
    | Supplier Management
    |--------------------------------------------------------------------------
    */
    Route::middleware(['verified', 'permission:suppliers.view'])->group(function () {
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
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
        'brands.index' => 'Brands',
        'categories.index' => 'Categories',
        'units.index' => 'Units',
        'products.index' => 'Products',
        'customers.index' => 'Customers',
        'warehouses.index' => 'Warehouses',
        'inventory.index' => 'Stock Levels',
        'stock-adjustments.index' => 'Stock Adjustments',
        'stock-transfers.index' => 'Stock Transfers',
        'purchase-orders.index' => 'Purchase Orders',
        'purchase-invoices.index' => 'Purchase Invoices',
        'purchase-returns.index' => 'Purchase Returns',
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

    foreach ($placeholders as $name => $label) {
        Route::get($name === 'reports.sales' ? 'reports/sales' : str_replace('.index', '', $name), [PlaceholderController::class, '__invoke'])
            ->defaults('module', $label)
            ->name($name);
    }
});
