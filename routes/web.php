<?php

declare(strict_types=1);

use App\Http\Controllers\Storefront\BasketController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
| Guests only — there is no customer login. Admin lives behind /admin in a
| separate route group with its own layout and asset build (phase 6).
|
| Every one of these is reachable from the site navigation. A route nobody can
| click is not a flow, it is a page.
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/shop', [CategoryController::class, 'index'])->name('shop.categories');
Route::get('/shop/{slug}', [CategoryController::class, 'show'])->name('shop.category');
Route::get('/product/{slug}', [ProductController::class, 'show'])->name('shop.product');
Route::get('/search', SearchController::class)->name('search');
Route::get('/cart', [BasketController::class, 'show'])->name('cart');
Route::post('/cart/add', [BasketController::class, 'add'])->name('cart.add');
Route::post('/cart/add-many', [BasketController::class, 'addMany'])->name('cart.add-many');
Route::post('/cart/{line}', [BasketController::class, 'update'])->name('cart.update');
Route::delete('/cart/{line}', [BasketController::class, 'remove'])->name('cart.remove');

// The fake payment step, and the receipt it produces. Addressed by ULID rather than
// listed at /receipts: an unauthenticated index would expose every customer's name,
// email and address. The staff list arrives with the admin login in phase 6.
Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');
Route::get('/receipt/{receipt}', [CheckoutController::class, 'show'])->name('receipt');

/*
 | Shop by vehicle. The picker cascades, so the dropdowns fetch each level as the
 | previous one is chosen. Selecting a vehicle is a FILTER, never a gate — clearing it
 | restores the full catalogue.
 */
Route::post('/vehicle', [VehicleController::class, 'select'])->name('vehicle.select');
Route::post('/vehicle/pick', [VehicleController::class, 'pick'])->name('vehicle.pick');
Route::get('/vehicle/make/{slug}', [VehicleController::class, 'byMake'])->name('vehicle.by-make');
Route::post('/vehicle/clear', [VehicleController::class, 'clear'])->name('vehicle.clear');
Route::get('/vehicle/makes', [VehicleController::class, 'makes'])->name('vehicle.makes');
Route::get('/vehicle/models/{make}', [VehicleController::class, 'models'])->name('vehicle.models');
Route::get('/vehicle/variants/{model}', [VehicleController::class, 'variants'])->name('vehicle.variants');

Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'submitContact'])->name('contact.submit');
Route::post('/newsletter', [PageController::class, 'subscribe'])
    ->middleware('throttle:10,1')
    ->name('newsletter.subscribe');
