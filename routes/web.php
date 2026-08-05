<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
| Phase 1 serves the homepage as static markup cut from the theme, to prove
| the Blade split renders identically to the original template. Controllers
| and live data arrive in phase 3.
*/

Route::view('/', 'home.index')->name('home');
