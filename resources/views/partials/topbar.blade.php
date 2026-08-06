    <div class="preloader-area">
        <img src="/assets/images/logo.png" alt="Logo">
    </div>

    <!-- page-direction -->
<!--
    <div class="page_direction">
        <div class="demo-rtl direction_switch"><button class="rtl">RTL</button></div>
        <div class="demo-ltr direction_switch"><button class="ltr">LTR</button></div>
    </div>
-->
    <!-- page-direction end -->

    <div class="h-infobox__wrapper">
        <div class="tt-header-holder h-infobox__popup">
            <div class="brator-search-area">
                <form class="search-form" role="search" method="get" action="{{ route('search', [], false) }}">
                    <input class="search-field" type="search" placeholder="Search by Part Name, Part Number, Vehicle and Brands" name="s" required="required">
                    <button type="submit">
                        <svg fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                            <path d="M62.1,57L44.6,42.8c3.2-4.2,5-9.3,5-14.7c0-6.5-2.5-12.5-7.1-17.1v0c-9.4-9.4-24.7-9.4-34.2,0C3.8,15.5,1.3,21.6,1.3,28                                c0,6.5,2.5,12.5,7.1,17.1c4.7,4.7,10.9,7.1,17.1,7.1c6.1,0,12.1-2.3,16.8-6.8l17.7,14.3c0.3,0.3,0.7,0.4,1.1,0.4                                c0.5,0,1-0.2,1.4-0.6C63,58.7,62.9,57.6,62.1,57z M10.8,42.7C6.9,38.8,4.8,33.6,4.8,28s2.1-10.7,6.1-14.6c4-4,9.3-6,14.6-6                                c5.3,0,10.6,2,14.6,6c3.9,3.9,6.1,9.1,6.1,14.6S43.9,38.8,40,42.7C32,50.7,18.9,50.7,10.8,42.7z"></path>
                        </svg>
                    </button>
                </form>
                <div class="search-quly">
                    <p>Quick Search:</p><a href="{{ route('search', ['s' => 'Replacement'], false) }}">Replacement</a><a href="{{ route('search', ['s' => 'Parts'], false) }}">Parts</a><a href="{{ route('search', ['s' => 'Brakes'], false) }}">Brakes</a><a href="{{ route('search', ['s' => 'Tires'], false) }}">Tires</a><a href="{{ route('search', ['s' => 'Fluids'], false) }}">Fluids</a><a href="{{ route('search', ['s' => 'Filters'], false) }}">Filters</a><a href="{{ route('search', ['s' => 'Wipers'], false) }}">Wipers</a>
                </div>
            </div>
            <div class="brator-header-menu-info text-left">@if (config('shop.contact.phone'))<span>Support:</span><a class="phomeee" href="tel:{{ preg_replace('/[^0-9+]/', '', (string) config('shop.contact.phone')) }}">{{ config('shop.contact.phone') }}</a>@else<span>Support:</span><a class="phomeee" href="{{ route('contact', [], false) }}">Contact us</a>@endif</div>
        </div>
    </div>
