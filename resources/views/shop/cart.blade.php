@extends('layouts.shop')

@section('title', 'Your Cart — Brator Auto Parts')

@section('content')
    <!-- bread start-->
    <div class="brator-breadcrumb-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="brator-breadcrumb">
                        <ul>
                            <li><a href="{{ route('home', [], false) }}">Home</a></li>
                            <li><a href="{{ route('shop.categories', [], false) }}">All Categories</a></li>
                            @foreach ($breadcrumbs ?? [] as $crumbLabel => $crumbUrl)
                                @if ($crumbUrl)
                                    <li><a href="{{ $crumbUrl }}">{{ $crumbLabel }}</a></li>
                                @else
                                    <li class="active-link">{{ $crumbLabel }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- bread end-->
    <div class="brator-cart-header-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-12">
                    <h3>Shopping Cart</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="brator-cart-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-xl-8 col-lg-12">
                    <div class="brator-cart-info">
                        <div class="brator-cart-h">
                            <h3>Your Cart</h3>
                        </div>
                        <div class="brator-cart-list">
                            <div class="brator-cart-list-items title-me">
                                <div class="brator-cart-list-items-title">
                                    <h6>product</h6>
                                </div>
                                <div class="brator-cart-list-items-price">
                                    <h6>price</h6>
                                </div>
                                <div class="brator-cart-list-items-qty-area">
                                    <h6>qty</h6>
                                </div>
                                <div class="brator-cart-list-items-subtotal">
                                    <h6>subtotal</h6>
                                </div>
                                <div class="brator-cart-list-items-removed"></div>
                            </div>
                            <div class="brator-cart-list-items">
                                <div class="brator-cart-list-items-title">
                                    <div class="img-cart"><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/cart-1.png" alt="alt" /></a></div>
                                    <div class="prodct-info">
                                        <h5><a href="#_">Silver with Mirror Cut Facewheels</a></h5>
                                        <p>19” DIAMETER (19” x 8.5”), White/Sliver</p><a href="#_">Edit </a>
                                    </div>
                                </div>
                                <div class="brator-cart-list-items-price">
                                    <p><sup>$204.05</sup><b class="pub">$1000</b></p>
                                </div>
                                <div class="brator-cart-list-items-qty-area">
                                    <div class="brator-cart-list-items-qty">
                                        <button class="decrement-count-qty">-</button>
                                        <input type="number" />
                                        <button class="add-count-qty">+</button>
                                    </div>
                                </div>
                                <div class="brator-cart-list-items-subtotal">
                                    <p>$816.2</p>
                                </div>
                                <div class="brator-cart-list-items-removed">
                                    <button>
                                        <svg class="bi bi-x" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="brator-cart-list-items">
                                <div class="brator-cart-list-items-title">
                                    <div class="img-cart"><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/cart-2.png" alt="alt" /></a></div>
                                    <div class="prodct-info">
                                        <h5><a href="#_">Automatic Proshift Shift Knob </a></h5>
                                        <p>19” DIAMETER (19” x 8.5”), White/Sliver</p><a href="#_">Edit </a>
                                    </div>
                                </div>
                                <div class="brator-cart-list-items-price">
                                    <p><sup>$204.05</sup><b class="pub">$1000</b></p>
                                </div>
                                <div class="brator-cart-list-items-qty-area">
                                    <div class="brator-cart-list-items-qty">
                                        <button class="decrement-count-qty">-</button>
                                        <input type="number" />
                                        <button class="add-count-qty">+</button>
                                    </div>
                                </div>
                                <div class="brator-cart-list-items-subtotal">
                                    <p>$816.2</p>
                                </div>
                                <div class="brator-cart-list-items-removed">
                                    <button>
                                        <svg class="bi bi-x" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="brator-cart-checkout">
                            <div class="brator-cart-checkout-left">
                                <div class="brator-cart-checkout-fields">
                                    <input type="text" placeholder="Enter Coupon Code" />
                                    <button>Apply Coupon</button>
                                </div>
                            </div>
                            <div class="brator-cart-checkout-right">
                                <div class="brator-cart-checkout-back"><a href="#_"> Continue Shopping</a></div>
                            </div>
                        </div>
                    </div>
@endsection
