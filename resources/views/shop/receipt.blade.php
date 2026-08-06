{{--
    The receipt confirmation page. The theme ships no order-confirmation screen, so
    this is assembled from classes it already has — the cart list rows for the line
    items and totals, the contact-form header for the message. No new CSS classes,
    which the fidelity test enforces.
--}}
@extends('layouts.shop')

@section('title', 'Receipt '.$receipt->receipt_number.' — Brator Auto Parts')

@section('content')
    <div class="brator-breadcrumb-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="brator-breadcrumb">
                        <ul>
                            <li><a href="{{ route('home', [], false) }}">Home</a></li>
                            <li><a href="{{ route('shop.categories', [], false) }}">All Categories</a></li>
                            <li class="active-link">Receipt {{ $receipt->receipt_number }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="brator-cart-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="brator-cart-info">
                        <div class="brator-contact-form-header">
                            <h2>Thank you, {{ $receipt->customer_name }}</h2>
                            <p>Your order is confirmed. Receipt <b>{{ $receipt->receipt_number }}</b> has been sent to {{ $receipt->customer_email }}. We will call you to arrange delivery and payment.</p>
                        </div>
                        <div class="brator-cart-list">
                            <div class="brator-cart-list-items title-me">
                                <div class="brator-cart-list-items-title"><h6>product</h6></div>
                                <div class="brator-cart-list-items-price"><h6>price</h6></div>
                                <div class="brator-cart-list-items-qty-area"><h6>qty</h6></div>
                                <div class="brator-cart-list-items-subtotal"><h6>subtotal</h6></div>
                            </div>
                            @foreach ($receipt->lines as $line)
                                <div class="brator-cart-list-items">
                                    <div class="brator-cart-list-items-title">
                                        <div class="prodct-info">
                                            <h5>{{ $line->product_name_snapshot }}</h5>
                                            <p>{{ $line->brand_name_snapshot }} &middot; SKU {{ $line->product_sku_snapshot }}</p>
                                        </div>
                                    </div>
                                    <div class="brator-cart-list-items-price"><p>{{ $line->unit_price_minor->format() }}</p></div>
                                    <div class="brator-cart-list-items-qty-area"><p>{{ $line->quantity }}</p></div>
                                    <div class="brator-cart-list-items-subtotal"><p>{{ $line->line_total_minor->format() }}</p></div>
                                </div>
                            @endforeach
                            <div class="brator-cart-list-items">
                                <div class="brator-cart-list-items-title"><div class="prodct-info"><h5>Subtotal</h5><p>excluding VAT</p></div></div>
                                <div class="brator-cart-list-items-subtotal"><p>{{ $receipt->subtotal_minor->format() }}</p></div>
                            </div>
                                @if (! $receipt->discount_minor->isZero())
                                    {{-- The receipt has to explain its own total. Without this row the
                                         VAT and the total look wrong against the subtotal. --}}
                                    <div class="brator-cart-list-items">
                                        <div class="brator-cart-list-items-title"><div class="prodct-info"><h5>Discount</h5><p>{{ $receipt->coupon_code }}</p></div></div>
                                        <div class="brator-cart-list-items-subtotal"><p>−{{ $receipt->discount_minor->format() }}</p></div>
                                    </div>
                                @endif
                            <div class="brator-cart-list-items">
                                <div class="brator-cart-list-items-title"><div class="prodct-info"><h5>VAT</h5><p>{{ (int) config('shop.vat_rate') }}%</p></div></div>
                                <div class="brator-cart-list-items-subtotal"><p>{{ $receipt->vat_minor->format() }}</p></div>
                            </div>
                            <div class="brator-cart-list-items">
                                <div class="brator-cart-list-items-title"><div class="prodct-info"><h5>Delivery</h5></div></div>
                                <div class="brator-cart-list-items-subtotal"><p>{{ $receipt->shipping_minor->isZero() ? 'Free' : $receipt->shipping_minor->format() }}</p></div>
                            </div>
                            <div class="brator-cart-list-items title-me">
                                <div class="brator-cart-list-items-title"><h6>total</h6></div>
                                <div class="brator-cart-list-items-subtotal"><h6>{{ $receipt->total_minor->format() }}</h6></div>
                            </div>
                        </div>
                        <div class="brator-cart-checkout">
                            <div class="brator-cart-checkout-right">
                                <div class="brator-cart-checkout-back"><a href="{{ route('shop.categories', [], false) }}"> Continue Shopping</a></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
