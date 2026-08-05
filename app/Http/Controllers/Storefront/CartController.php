<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use Illuminate\View\View;

final class CartController
{
    /**
     * The cart page renders the theme's markup. Adding, removing and totalling is
     * phase 5 (basket + dummy checkout) — this exists now so the header's cart link
     * and the nav are a working flow rather than dead ends.
     */
    public function index(): View
    {
        return view('shop.cart', ['breadcrumbs' => ['Your Cart' => null]]);
    }
}
