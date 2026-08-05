<?php

declare(strict_types=1);

use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
| Guests only — there is no customer login. Admin lives behind /admin in a
| separate route group with its own layout and asset build (phase 6).
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/shop', [CategoryController::class, 'index'])->name('shop.categories');
Route::get('/shop/{slug}', [CategoryController::class, 'show'])->name('shop.category');
Route::get('/product/{slug}', [ProductController::class, 'show'])->name('shop.product');
