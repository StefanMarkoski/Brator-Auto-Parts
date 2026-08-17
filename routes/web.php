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
/*
 | Discount codes. DECLARED BEFORE /cart/{line}, and that ordering is the whole point:
 | "/cart/coupon" matches the {line} wildcard too, so with the routes the other way round
 | applying a code ran the line-update action with $line = "coupon", found nothing, and
 | redirected in silence. The same trap as products/create being read as a product id.
 |
 | POST rather than a link because it changes session state.
*/
/*
 | BOTH COUPON-READING ENDPOINTS ARE THROTTLED, and that is not boilerplate.
 |
 | Codes are SAVE<percent> plus filler from a 30-character alphabet (GenerateCouponAction), which
 | is 30^4 = 810 000 candidates for a two-digit percentage, behind a prefix set anybody could
 | guess. Until now POST /cart/coupon had no limit at all: measured, forty wrong codes posted
 | back to back ALL returned 302, while the identical burst against /newsletter started refusing
 | at the eleventh. The guessing oracle already existed, at one request per guess, before the
 | live check below was written — this closes it and keeps the new endpoint from reopening it.
 |
 | Twenty and thirty a minute are generous for somebody typing one code and useless for walking a
 | code space. The check gets the larger allowance because a debounced field legitimately asks
 | more than once while a ten-character code is being typed.
*/
Route::post('/cart/coupon', [BasketController::class, 'applyCoupon'])
    ->middleware('throttle:20,1')
    ->name('cart.coupon.apply');
/*
 | Two segments, so it cannot be swallowed by the /cart/{line} wildcard below — and it is a GET,
 | which those are not. Declared here anyway, beside the other coupon routes, so the whole
 | feature is in one place.
*/
Route::get('/cart/coupon/check', [BasketController::class, 'checkCoupon'])
    ->middleware('throttle:30,1')
    ->name('cart.coupon.check');
Route::delete('/cart/coupon', [BasketController::class, 'removeCoupon'])->name('cart.coupon.remove');

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

/*
 | "Clear all filters" is a POST, not a link.
 |
 | It has to clear the chosen vehicle, which lives in the session, and a GET that mutates
 | session state is both wrong and liable to be fired by a link prefetcher.
 */
Route::post('/filters/clear', [VehicleController::class, 'clearFilters'])->name('filters.clear');
Route::get('/vehicle/makes', [VehicleController::class, 'makes'])->name('vehicle.makes');
Route::get('/vehicle/models/{make}', [VehicleController::class, 'models'])->name('vehicle.models');
Route::get('/vehicle/variants/{model}', [VehicleController::class, 'variants'])->name('vehicle.variants');

Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'submitContact'])->name('contact.submit');
Route::post('/newsletter', [PageController::class, 'subscribe'])
    ->middleware('throttle:10,1')
    ->name('newsletter.subscribe');
