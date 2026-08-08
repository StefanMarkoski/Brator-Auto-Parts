{{--
    "Shop by Make" — the vehicle makes the shop actually has parts for.

    WHAT WAS REMOVED, and why it was not just cosmetic:

    Thirteen hardcoded tiles sat after the real ones — Chevy, Ford, Dodge, Huyndai, Kia,
    Mercerdess, BMW, Audi, Lexus, Jaguar, Volvo 2, Rangover, Porsche, the theme's typos
    included. They carry the theme's `disable` class, which is `display: none` until its own
    script adds `current`, so "view more 2" revealed thirteen manufacturers this shop does not
    stock, every one of them linking to the whole catalogue rather than a make filter. The
    button went with them: there is nothing left to reveal, and the real makes are all shown.

    The second tab, "Featured Models", was worse than fiction. tab.js counts TITLES and then
    indexes into CONTENT panes, so clicking a second title with only one pane threw a
    TypeError after hiding every pane — one click blanked the whole section. There is no
    models pane to show, so the title is gone.

    The heading now comes from the homepage editor, which offered a Heading box for this
    section and printed it nowhere.
--}}
    <!-- Brator featured makes list start -->
    <div class="brator-makes-list-area design-two">
        <div class="container-xxxl container-xxl container">
            <div class="brator-brator-makes-list-tab-list js-tabs" id="tabs-product-content">
                <div class="brator-makes-list-tab-header js-tabs__header">
                    <ul>
                        <li><a class="js-tabs__title" href="{{ route('shop.categories', [], false) }}">{{ $section->heading ?? 'Featured Makes' }}</a></li>
                    </ul>
                </div>
                <div class="row js-tabs__content">
                    <div class="col-md-12">
                        <div class="brator-makes-list">
                            @foreach ($section->items as $make)
                                <div class="brator-makes-list-single">
                                    <a href="{{ route('vehicle.by-make', $make->slug, false) }}">
                                        <span>{{ $make->name }}</span>
                                        <svg class="bi bi-chevron-right" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"></path>
                                        </svg>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Brator featured makes list end -->
