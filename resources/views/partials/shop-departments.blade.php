{{--
    The listing sidebar's "Categories" widget.

    The theme shipped this as a static list of its own demo departments — Auto Parts, Car
    Care, Fluids & Chemicals, Oils, Tools & Supplies, Clearance — with "Tires" and "wheels"
    each repeated twice under two different parents, and every entry either a dead #_ link or
    a link back to the homepage. A shopper reading the category list on a listing page was
    reading another site's catalogue.

    Note this is the SECOND category widget on the page: partials.filter-sidebar already
    lists the current department's sub-categories. That one narrows within where you are;
    this one moves you between departments, which is why both earn their place.

    Markup and classes are the theme's own — the same shop-cat-list / sub-cat structure — so
    nothing is restyled.
--}}
<div class="brator-sidebar-single-item">
    <div class="shop-sidebar-title">
        <h2>Categories</h2>
    </div>
    <div class="shop-sidebar-content">
        <div class="shop-cat-list">
            <ul>
                <li><a href="{{ route('shop.categories', [], false) }}">All Categories</a></li>

                @foreach ($navCategories as $department)
                    @if ($department['children'] === [])
                        <li><a href="{{ route('shop.category', $department['slug'], false) }}">{{ $department['name'] }}</a></li>
                    @else
                        <li class="sub-cat">
                            <a href="{{ route('shop.category', $department['slug'], false) }}">{{ $department['name'] }}</a>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"></path>
                            </svg>
                            <ul>
                                @foreach ($department['children'] as $child)
                                    <li><a href="{{ route('shop.category', $child['slug'], false) }}">{{ $child['name'] }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    </div>
</div>
