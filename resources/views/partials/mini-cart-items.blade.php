{{--
    The header's mini-cart. The theme shipped two hardcoded wheels here, complete with a
    "Discount - $5.00" line for a discount feature that does not exist — so the panel
    told every visitor they had four wheels in a basket they had never touched.

    Built from the theme's own classes; the edit link goes to the product and the close
    button removes the line for real.
--}}
@forelse ($miniCart->lines as $line)
    <div class="brator-cart-item-list-item">
        <div class="brator-cart-item-list-item-img">
            <a href="{{ route('shop.product', $line->productSlug, false) }}"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $line->imagePath }}" alt="{{ $line->productName }}" /></a>
        </div>
        <div class="brator-cart-item-list-item-title">
            <div class="brator-cart-item-list-item-title-one">
                <a href="{{ route('shop.product', $line->productSlug, false) }}">
                    <h2>{{ $line->productName }}</h2>
                </a>
                <div class="price-pdo">
                    <h4>{{ $line->quantity }}</h4>
                    <h5>x</h5>
                    <h6><span>{{ $line->unitPrice->format() }}</span></h6>
                </div>
            </div>
            <div class="brator-cart-item-list-item-title-accetion">
                <p>{{ $line->brandName }} &middot; SKU {{ $line->productSku }}</p>
                <div class="brator-cart-item-list-item-title-accetion-part">
                    <a href="{{ route('shop.product', $line->productSlug, false) }}">
                        <svg class="bi bi-pencil-square" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"></path>
                            <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"></path>
                        </svg></a>
                    <form method="post" action="{{ route('cart.remove', $line->lineId, false) }}">
                        @csrf
                        @method('DELETE')
                        <button class="cart-item-close" type="submit" aria-label="Remove {{ $line->productName }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="brator-cart-item-list-item">
        <div class="brator-cart-item-list-item-title">
            <div class="brator-cart-item-list-item-title-one">
                <h2>Your cart is empty</h2>
            </div>
        </div>
    </div>
@endforelse
