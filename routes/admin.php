<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HeroImageController;
use App\Http\Controllers\Admin\HomepageController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductPhotoController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\VehicleLookupController;
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
        Route::put('receipts/{receipt}/status', [ReceiptController::class, 'updateStatus'])
            ->name('receipts.status');
        Route::put('receipts/{receipt}/notes', [ReceiptController::class, 'updateNotes'])
            ->name('receipts.notes');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        // Declared before products/{product}/edit, or "create" is matched as a product id.
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');
        Route::delete('products/{product}/override', [ProductController::class, 'releaseOverride'])
            ->name('products.override.release');

        Route::post('products/{product}/images', [ProductController::class, 'storeImages'])
            ->name('products.images.store');
        Route::put('products/{product}/images/{image}', [ProductController::class, 'updateImage'])
            ->name('products.images.update');
        Route::delete('products/{product}/images/{image}', [ProductController::class, 'destroyImage'])
            ->name('products.images.destroy');

        Route::get('categories', [CatalogController::class, 'categories'])->name('categories.index');
        Route::post('categories', [CatalogController::class, 'storeCategory'])->name('categories.store');
        Route::put('categories/{category}', [CatalogController::class, 'updateCategory'])
            ->name('categories.update');
        Route::delete('categories/{category}', [CatalogController::class, 'destroyCategory'])
            ->name('categories.destroy');

        Route::get('brands', [CatalogController::class, 'brands'])->name('brands.index');
        Route::post('brands', [CatalogController::class, 'storeBrand'])->name('brands.store');
        Route::put('brands/{brand}', [CatalogController::class, 'updateBrand'])->name('brands.update');
        Route::delete('brands/{brand}', [CatalogController::class, 'destroyBrand'])->name('brands.destroy');

        Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
        Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
        Route::put('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');

        Route::get('homepage', [HomepageController::class, 'index'])->name('homepage.index');
        Route::put('homepage/{section}', [HomepageController::class, 'update'])->name('homepage.update');
        Route::put('homepage/{section}/move', [HomepageController::class, 'move'])->name('homepage.move');

        /*
         | Hero pictures. Throttled because each POST makes the server download a file from an
         | address the poster chose, and an unthrottled endpoint of that shape is a free
         | outbound-request generator for anybody who reaches the admin.
         */
        Route::post('homepage/hero-images', [HeroImageController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('homepage.hero-images.store');
        Route::delete('homepage/hero-images/{banner}', [HeroImageController::class, 'destroy'])
            ->name('homepage.hero-images.destroy');

        /*
         | The fitment picker's cascade. Read-only JSON, staff-only like everything else in
         | this group — the vehicle tree is not secret, but an endpoint is a surface and there
         | is no reason for this one to be open.
         */
        Route::get('vehicles/years', [VehicleLookupController::class, 'years'])->name('vehicles.years');
        Route::get('vehicles/makes', [VehicleLookupController::class, 'makes'])->name('vehicles.makes');
        Route::get('vehicles/models/{make}', [VehicleLookupController::class, 'models'])->name('vehicles.models');
        Route::get('vehicles/sub-models/{model}', [VehicleLookupController::class, 'subModels'])->name('vehicles.sub-models');
        Route::get('vehicles/engines/{model}', [VehicleLookupController::class, 'engines'])->name('vehicles.engines');

        /*
         | Bulk product photos, one set per department. Throttled: each POST makes the server
         | download from an address the poster chose, and then writes thousands of rows.
         */
        Route::get('product-photos', [ProductPhotoController::class, 'index'])->name('product-photos.index');
        // Declared before the {department} route, or "all" is matched as a department id.
        Route::post('product-photos/all', [ProductPhotoController::class, 'storeAll'])
            ->middleware('throttle:20,1')
            ->name('product-photos.all');
        Route::post('product-photos/{department}', [ProductPhotoController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('product-photos.store');
        Route::delete('product-photos/{department}', [ProductPhotoController::class, 'destroy'])
            ->name('product-photos.destroy');

        Route::get('imports', [CatalogController::class, 'imports'])->name('imports.index');

        /*
         | The import runner: upload stages the file, show previews it, apply commits it.
         | Three URLs because they are three decisions, and the middle one is the point.
         */
        Route::post('imports', [ImportController::class, 'upload'])->name('imports.upload');
        Route::get('imports/{run}', [ImportController::class, 'show'])->name('imports.show');
        Route::post('imports/{run}/apply', [ImportController::class, 'apply'])->name('imports.apply');

        /*
         | Undo an applied import. DELETE, because it destroys rows — and a GET that deleted
         | products would be fired by any link prefetcher that wandered into the panel.
         */
        Route::delete('imports/{run}', [ImportController::class, 'destroy'])->name('imports.destroy');
    });
});
