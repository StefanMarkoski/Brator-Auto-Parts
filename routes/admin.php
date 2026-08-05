<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Middleware\EnsureUserIsStaff;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
| A separate route group with its own layout and its own asset build. Nothing
| here shares a view, a partial or a stylesheet with the storefront — Tailwind's
| global reset would flatten the purchased theme anywhere the two met.
|
| Staff only. This shop has no customer accounts, so a login here is always staff.
*/

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        // Throttled: a login form without a rate limit is an invitation to guess
        // passwords all afternoon.
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:6,1')
            ->name('login.attempt');
    });

    Route::middleware(['auth', EnsureUserIsStaff::class])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts.index');
        Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}/override', [ProductController::class, 'releaseOverride'])
            ->name('products.override.release');

        Route::get('categories', [CatalogController::class, 'categories'])->name('categories.index');
        Route::get('brands', [CatalogController::class, 'brands'])->name('brands.index');
        Route::get('imports', [CatalogController::class, 'imports'])->name('imports.index');
    });
});
