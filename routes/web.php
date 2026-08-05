<?php

declare(strict_types=1);

use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\SearchController;
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
Route::get('/cart', [CartController::class, 'index'])->name('cart');

Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'submitContact'])->name('contact.submit');
