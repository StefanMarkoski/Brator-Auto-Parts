/*
 | Storefront progressive enhancement.
 |
 | WHY THIS EXISTS, and why it is not Alpine.
 |
 | The interactive bits were first written with Alpine directives (x-on, x-model, x-show,
 | x-text) — and Alpine ships only in the ADMIN bundle. The storefront loads the purchased
 | theme's jQuery and nothing else, so every one of those directives was inert: the vehicle
 | cascade never advanced, filter checkboxes never applied, the sort select did nothing, the
 | brand search box did nothing, and the bundle total never moved.
 |
 | Rather than ship a second framework onto pages already running the theme's jQuery — the
 | collision I argued against when isolating the admin panel — this is a small file with no
 | dependencies of its own, served straight off disk like the theme's assets. No bundler
 | touches the storefront, which is the promise that keeps it byte-identical to what was
 | bought.
 |
 | Everything here is ENHANCEMENT. Every form still works with JavaScript disabled, because
 | every one of them has a real submit button.
 */
(function () {
    'use strict';

    /*
     | Set by bindSmoothListing on a listing page, so the auto-submit binding above can
     | hand a URL to the in-place update instead of reloading. Null everywhere else, and
     | returns false for anything it will not handle — the caller then navigates normally.
    */
    var listingNavigate = null;

    /*
     | Set by bindScrollMemory once it has bound, so the in-place cart's fallback path can record
     | the scroll position before it hands over to a real submit.
     |
     | It needs to be reachable from there because that fallback uses form.submit(), which fires
     | NO submit event — so the listener bindScrollMemory puts on the document never sees it, and
     | without this the one path most likely to reload is the one path that forgets where you
     | were. Found by measuring: scrolled to 900, forced the fallback, came back at 0.
     |
     | A no-op until bindScrollMemory runs, and on any page without a [data-scroll-memory] region.
    */
    var rememberScrollPosition = function () {};

    /* ------------------------------------------------------------------ *
     | "Meyle Seat Cover Set — 3.522,10 ден added to your cart." — where that sentence goes.
     |
     | THIS IS WHAT REPLACED "add to cart, get thrown onto /cart". Stefan's objection was that
     | being redirected after every add is a shop arguing with the person browsing it, and he is
     | right — the confirmation belongs where the shopper already is.
     |
     | THE FIRST ATTEMPT PUT IT IN THE MINI-CART PANEL AND THAT WAS WRONG, and not subtly wrong.
     | It worked exactly as designed — right sentence, right money, opened on time, closed after
     | five seconds — and Stefan still reported it as not working, because it was rendering in
     | the HEADER. Measured: the message sat at y = -2500, two and a half thousand pixels above
     | the top of the window, while he was scrolled down a listing looking at the button he had
     | just pressed. My own verification had measured how long it stayed open and never once
     | measured where it was.
     |
     | So: fixed to the bottom of the VIEWPORT, which is the only place that is on screen no
     | matter how far down the page somebody has scrolled. Bottom rather than top because the
     | header is where the shop talks about itself and the bottom is out of the way of what
     | they are reading.
     |
     | WHY IT IS BUILT HERE RATHER THAN RENDERED BY BLADE. It carries a handful of declarations
     | the purchased theme has no class for — position: fixed, the slide-up, the shadow. This
     | project has no stylesheet of its own, and the standing rule is that no new CSS class
     | enters the server-rendered markup, so the alternative was a <style> block in the layout
     | for one element that only ever exists after JavaScript has run. An element created here,
     | styled inline, using the theme's own #f73312 — nothing to keep in sync, and nothing for
     | ThemeFidelityTest to trip over, because it never appears in a server response.
     |
     | ONE TOAST, REUSED. Adding three parts in a row replaces the sentence and restarts the
     | clock rather than stacking three boxes up the side of the window.
     * ------------------------------------------------------------------ */
    var basketToast = null;
    var basketToastTimer = null;

    function showBasketToast(message) {
        if (!message) return;

        if (basketToast === null) {
            basketToast = document.createElement('div');

            // role=status, not alert: a confirmation somebody just asked for by pressing a
            // button is announced politely, without interrupting whatever is being read.
            basketToast.setAttribute('role', 'status');
            basketToast.setAttribute('data-basket-toast', '');
            basketToast.style.cssText = [
                'position: fixed',
                'left: 50%',
                'bottom: 24px',
                'z-index: 9999',
                'max-width: min(520px, calc(100vw - 32px))',
                'padding: 14px 20px',
                'background: #f73312',
                'color: #fff',
                'font-size: 15px',
                'font-weight: 600',
                'line-height: 1.4',
                'border-radius: 4px',
                'box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28)',
                'text-align: center',
                // The transform carries the centring as well as the slide, so both live in one
                // property and cannot fight each other mid-transition.
                'transform: translate(-50%, 16px)',
                'opacity: 0',
                'transition: opacity 0.25s, transform 0.25s',
                // Never in the way of the page underneath: there is nothing to click on it, and
                // it must not swallow a click meant for whatever it is covering.
                'pointer-events: none',
            ].join(';');

            document.body.appendChild(basketToast);
        }

        // textContent: the sentence is the server's flash message and must never be parsed as
        // markup, whatever ends up in a product name.
        basketToast.textContent = message;

        /*
         | Two frames before the visible state is written. One is not enough — the element was
         | only just inserted, and a transition from a style the browser has not computed yet
         | does not run at all; the toast would appear instantly instead of rising into place.
        */
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                if (basketToast === null) return;

                basketToast.style.opacity = '1';
                basketToast.style.transform = 'translate(-50%, 0)';
            });
        });

        if (basketToastTimer !== null) window.clearTimeout(basketToastTimer);

        basketToastTimer = window.setTimeout(function () {
            basketToastTimer = null;

            if (basketToast === null) return;

            basketToast.style.opacity = '0';
            basketToast.style.transform = 'translate(-50%, 16px)';
        }, 4000);
    }

    /* ------------------------------------------------------------------ *
     | Auto-submit: a select or checkbox that applies itself on change.
     | Used by the vehicle cascade, the filter sidebar, and the sort select.
     * ------------------------------------------------------------------ */
    function bindAutoSubmit() {
        document.querySelectorAll('[data-auto-submit]').forEach(function (el) {
            if (el.dataset.autoSubmitBound) return;
            el.dataset.autoSubmitBound = '1';

            function apply() {
                // A select whose value is a URL navigates; anything else submits its form.
                if (el.dataset.autoSubmit === 'navigate' && el.value) {
                    // On a listing page the sort select changes the same result set, so it
                    // goes through the in-place update rather than reloading the document.
                    // Anywhere else — and if that update declines the URL — it is a plain
                    // navigation, exactly as before.
                    if (!listingNavigate || !listingNavigate(el.value)) {
                        window.location = el.value;
                    }

                    return;
                }

                var form = el.form || el.closest('form');
                if (!form) return;

                // requestSubmit runs validation and fires submit handlers; submit() does
                // neither. Fall back for older engines.
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }

            el.addEventListener('change', apply);

            /*
             | select2 does NOT dispatch a native change event — it calls jQuery handlers
             | only. The theme runs select2() on every .brator-select-active, which is every
             | dropdown in the vehicle picker, so addEventListener('change') never fired and
             | the cascade never advanced no matter what I did to the markup.
             |
             | That is the bug Stefan reported. My first fix missed it because I "verified"
             | by dispatching a native event by hand — precisely the path a real user does
             | not take. Test the way the user acts, not the way the code expects.
             |
             | jQuery is already present (the theme depends on it), so bind through it as
             | well. Guarded, so this file still works if jQuery ever goes away.
             */
            if (window.jQuery) {
                window.jQuery(el).on('change', function (event) {
                    // Skip jQuery's replay of a genuine native event, or we submit twice.
                    if (event.originalEvent) return;

                    apply();
                });
            }
        });
    }

    /* ------------------------------------------------------------------ *
     | Client-side list filter, for the sidebar's brand search box. The whole
     | list is already on the page, so this must not hit the server.
     * ------------------------------------------------------------------ */
    function bindListFilters() {
        document.querySelectorAll('[data-filter-input]').forEach(function (input) {
            if (input.dataset.filterBound) return;
            input.dataset.filterBound = '1';

            var scope = input.closest('[data-filter-scope]') || document;

            input.addEventListener('input', function () {
                var needle = input.value.trim().toLowerCase();

                scope.querySelectorAll('[data-filter-label]').forEach(function (row) {
                    var label = row.getAttribute('data-filter-label').toLowerCase();
                    row.style.display = (needle === '' || label.indexOf(needle) !== -1) ? '' : 'none';
                });
            });
        });
    }

    /* ------------------------------------------------------------------ *
     | "Frequently Bought Together": recompute the combined total as items are
     | ticked. Rendered correct server-side first, so this only reacts.
     * ------------------------------------------------------------------ */
    function bindBundleTotals() {
        document.querySelectorAll('[data-bundle]').forEach(function (bundle) {
            if (bundle.dataset.bundleBound) return;
            bundle.dataset.bundleBound = '1';

            var output = bundle.querySelector('[data-bundle-total]');
            var boxes = bundle.querySelectorAll('[data-bundle-price]');
            if (!output || !boxes.length) return;

            var symbol = bundle.getAttribute('data-currency') || '';

            function recompute() {
                var minor = 0;

                boxes.forEach(function (box) {
                    if (box.checked) {
                        minor += parseInt(box.getAttribute('data-bundle-price'), 10) || 0;
                    }
                });

                // Formatted the way the server does: dot thousands, comma decimals.
                var parts = (minor / 100).toFixed(2).split('.');
                var whole = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                output.textContent = whole + ',' + parts[1] + ' ' + symbol;
            }

            boxes.forEach(function (box) {
                box.addEventListener('change', recompute);
            });

            recompute();
        });
    }

    /* ------------------------------------------------------------------ *
     | The price range filter, driven by the theme's OWN noUiSlider.
     |
     | The first attempt put two number inputs in the price block, and they
     | rendered stacked on the same rectangle — the theme's CSS expects a slider
     | there, not inputs, so the control could not be operated with a mouse at
     | all. Stefan chose to drive the real slider rather than add CSS, which is
     | the right call: it looks exactly as designed and needs no new styling.
     |
     | The hidden inputs carry the values, so the form still submits a usable
     | range with JavaScript off.
     * ------------------------------------------------------------------ */
    function bindPriceSlider() {
        var mount = document.querySelector('[data-price-slider]');
        if (!mount || typeof window.noUiSlider === 'undefined') return;
        if (mount.dataset.sliderBound) return;
        mount.dataset.sliderBound = '1';

        var form = mount.closest('form');
        var minInput = form && form.querySelector('[data-price-min]');
        var maxInput = form && form.querySelector('[data-price-max]');
        var readout = mount.parentElement.querySelector('[data-price-readout]');
        if (!minInput || !maxInput) return;

        var floor = parseInt(mount.getAttribute('data-price-floor'), 10) || 0;
        var ceiling = parseInt(mount.getAttribute('data-price-ceiling'), 10) || 0;
        if (ceiling <= floor) return;

        var symbol = mount.getAttribute('data-currency') || '';
        var from = parseInt(minInput.value, 10);
        var to = parseInt(maxInput.value, 10);

        var options = {
            start: [isNaN(from) ? floor : from, isNaN(to) ? ceiling : to],
            connect: true,
            step: 100,
            range: { min: floor, max: ceiling },
        };

        /*
         | The theme creates this slider itself, on DOMContentLoaded, with a placeholder
         | 0-100 range. noUiSlider THROWS if something creates it twice.
         |
         | This used to create the slider when it did not yet exist — and because both
         | scripts are deferred and both run on DOMContentLoaded, whichever went first won.
         | When it was this one, the theme's own create() then threw
         | "Slider was already initialized", which killed the rest of brator-script.js from
         | that line on. Ten of those in the console on a single browsing session, and
         | anything the theme initialises after its slider silently never ran.
         |
         | So: never create it here. bindPriceSlider is called on window.load, which is after
         | every DOMContentLoaded handler, so the theme has always been and gone. The create
         | below is only a last resort for the case where the theme's script is absent.
        */
        if (mount.noUiSlider) {
            mount.noUiSlider.updateOptions(options, true);
        } else {
            window.noUiSlider.create(mount, options);
        }

        function label(values) {
            if (!readout) return;
            readout.textContent = Math.round(values[0]).toLocaleString('mk-MK') + ' - ' +
                Math.round(values[1]).toLocaleString('mk-MK') + ' ' + symbol;
        }

        function writeInputs(values) {
            minInput.value = Math.round(values[0]);
            maxInput.value = Math.round(values[1]);
        }

        /*
         | THE READOUT ON EVERY UPDATE; THE INPUTS ONLY WHEN A HUMAN MOVES THE SLIDER.
         |
         | Both inputs used to be written from 'update' — which noUiSlider also fires on init,
         | before anyone has touched anything. So the inputs always held the current bounds, and
         | since they sit inside the filter form that apply() serialises whole, every filter
         | request carried a price band nobody had asked for. hasAnyNarrowing() then stayed true
         | forever, and because the bounds are computed from the FILTERED set, unticking your
         | only filter re-applied that filter's price range to the whole catalogue.
         |
         | The view now renders these inputs empty unless a price filter is really set. This is
         | the other half of that fix: without it the slider fills them straight back in.
         |
         | 'slide' is drag only, 'change' is release-or-tap — both user-driven. 'update' covers
         | those AND programmatic changes, so it is right for the label and wrong for the inputs.
        */
        mount.noUiSlider.on('update', label);
        mount.noUiSlider.on('slide', writeInputs);

        // Apply only when the handle is released, not on every pixel of the drag.
        mount.noUiSlider.on('change', function (values) {
            writeInputs(values);

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    }

    /* ------------------------------------------------------------------ *
     | The product page's +/- quantity buttons.
     |
     | The theme ships this control on the product page and never binds it —
     | grep its own JS for decrement-count-qty and there are no matches — so
     | both buttons were decoration. The number input beside them always
     | worked, which is exactly why nobody noticed.
     |
     | Bound via data-qty-step rather than by class or by type="button". The
     | CART page's +/- carry the same classes but are real submits posting
     | name="step", and hijacking those would break a control that works.
     * ------------------------------------------------------------------ */
    function bindQuantitySteppers() {
        document.querySelectorAll('[data-qty-step]').forEach(function (button) {
            if (button.dataset.qtyBound) return;
            button.dataset.qtyBound = '1';

            button.addEventListener('click', function () {
                var wrapper = button.parentElement;
                var input = wrapper && wrapper.querySelector('input[type="number"]');
                if (!input) return;

                var step = parseInt(button.getAttribute('data-qty-step'), 10) || 0;
                var min = input.min === '' ? 1 : parseInt(input.min, 10);
                var max = input.max === '' ? Infinity : parseInt(input.max, 10);
                var next = (parseInt(input.value, 10) || min) + step;

                // Clamped, so the field cannot reach 0 or a stock-exceeding number and
                // then be rejected only after the shopper has pressed Add to Cart.
                input.value = Math.min(max, Math.max(min, next));

                // Dispatched so anything else watching the field (a total, a validity
                // message) reacts as it would to typing.
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    /* ------------------------------------------------------------------ *
     | Paint the hero's first picture straight away.
     |
     | FOUND BY SCREENSHOTTING THE HOMEPAGE, not by reading it: the hero was BLANK WHITE for
     | the first five seconds, with the white headline invisible on it, and then the picture
     | appeared — because the first rotation tick was what finally set it.
     |
     | The theme lazy-loads this background: the banner carries data-bg and the theme's own
     | `lazybeforeunveil` listener copies it onto style.backgroundImage. Measured, that listener
     | never landed — the element ends up with lazysizes' `lazyloaded` class and
     | backgroundImage: none — so nothing painted the hero until commit() ran at the five-second
     | mark and removed data-bg out from under it.
     |
     | Rather than chase that race, the hero stops being lazy. It is the first thing above the
     | fold on the landing page, so there was never anything to gain by deferring it, and every
     | rotation picture is preloaded a few lines below anyway. data-bg is cleared for the same
     | reason commit() clears it: so the theme's listener cannot later repaint picture one over
     | whichever one is showing.
     *------------------------------------------------------------------ */
    function paintHeroBackground() {
        document.querySelectorAll('.brator-main-banner-area[data-bg]').forEach(function (banner) {
            var src = banner.getAttribute('data-bg');
            if (!src) return;

            banner.style.backgroundImage = 'url("' + src + '")';
            banner.removeAttribute('data-bg');
        });
    }

    /* ------------------------------------------------------------------ *
     | The homepage hero: rotate its background picture.
     |
     | The theme renders this banner as a background with the headings and the
     | vehicle picker layered on top, so there is no slider to drive — the
     | picture IS the container's background-image.
     |
     | THE CROSS-FADE, and why it needs a second layer.
     |
     | background-image cannot be transitioned: CSS has no way to interpolate
     | between two pictures, so a `transition` on it does nothing and the swap is
     | a hard cut. Fading the banner's own opacity is not an alternative either —
     | that would fade the headings and the vehicle picker along with it.
     |
     | So a plain div is laid over the banner, given the NEXT picture, and faded
     | from transparent to opaque. When it lands, the banner takes that picture as
     | its own background and the layer is reset to invisible without a
     | transition, ready for the next one. The result is a cross-fade in which
     | only the photograph moves.
     |
     | The layer is built HERE rather than in the Blade file on purpose: it is
     | pure presentation with no meaning in the markup, it must not exist for
     | anyone without JavaScript, and keeping it in script means the template and
     | the purchased stylesheets are both left alone. It carries no class, so it
     | inherits nothing and collides with nothing.
     |
     | Every image is preloaded before the first swap. Assigning a
     | background-image the browser has not fetched paints the element empty
     | while it downloads, which on a hero reads as the page breaking.
     * ------------------------------------------------------------------ */
    function bindHeroRotation() {
        document.querySelectorAll('[data-hero-rotate]').forEach(function (banner) {
            if (banner.dataset.heroBound) return;
            banner.dataset.heroBound = '1';

            var images;
            try {
                images = JSON.parse(banner.getAttribute('data-hero-rotate'));
            } catch (e) {
                // A malformed list leaves the banner exactly as the server rendered it:
                // one background, no rotation. Never a blank hero.
                return;
            }

            if (!Array.isArray(images) || images.length < 2) return;

            var dots = banner.querySelectorAll('[data-hero-page]');
            var interval = parseInt(banner.getAttribute('data-hero-interval'), 10) || 5000;
            var fade = parseInt(banner.getAttribute('data-hero-fade'), 10);
            var current = 0;
            var timer = null;
            var fadeToken = 0;
            var showToken = 0;
            var waiting = false;

            if (isNaN(fade)) fade = 900;

            /*
             | Somebody who has asked their system for less animation gets the picture
             | swapped outright. A cross-fade is decoration; the pictures are the content.
            */
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                fade = 0;
            }

            /* ---------------------------------------------------------------- *
             | EVERY PICTURE IS TRACKED, AND NONE IS PAINTED BEFORE THE BROWSER HAS IT.
             |
             | Assigning a background-image the browser has not fetched paints the element
             | EMPTY while it downloads. On this hero that reads as the shop being broken,
             | because the headline is white: a blank banner is a blank white screen with no
             | text on it at all.
             |
             | MEASURED before this existed, on a 150 kbps link with cold pictures and warm
             | scripts: clicking the fourth dot put picture four on the fade layer immediately,
             | faded a transparent layer in over 900ms, then committed the unfetched picture to
             | the banner — 2.6 seconds of white with the headline invisible, ending only when
             | the bytes arrived. That was the half of Stefan's "whitelabel" report which
             | survived the first-paint fix.
             * ---------------------------------------------------------------- */
            var ready = {};
            var broken = {};
            var loaders = {};

            /*
             | Calls back with TRUE when the browser holds a decoded picture, FALSE when that
             | picture is never coming. Both answers matter: false is how a 404 or a dead
             | connection stops the rotation waiting on it forever, and it must NOT be treated
             | as "go ahead and paint" — painting a picture that failed is the blank hero all
             | over again, just with a different cause.
            */
            function ensure(src, then) {
                if (ready[src] || broken[src]) {
                    if (then) then(!!ready[src]);

                    return;
                }

                var loader = loaders[src];

                if (!loader) {
                    var image = new Image();

                    loader = loaders[src] = { image: image, waiting: [] };

                    var settle = function (ok) {
                        if (ok) {
                            ready[src] = true;
                        } else {
                            broken[src] = true;
                        }

                        var queue = loader.waiting;
                        loader.waiting = [];
                        queue.forEach(function (fn) { fn(ok); });
                    };

                    /*
                     | decode() rather than load alone: load means the bytes have arrived,
                     | decode means the next paint will not stall on them. Guarded because it is
                     | not in every engine — and a decode that rejects counts as broken, because
                     | a picture that cannot be decoded cannot be shown.
                    */
                    image.onload = function () {
                        if (typeof image.decode === 'function') {
                            image.decode().then(
                                function () { settle(true); },
                                function () { settle(false); }
                            );
                        } else {
                            settle(true);
                        }
                    };

                    image.onerror = function () { settle(false); };
                    image.src = src;
                }

                if (then) loader.waiting.push(then);
            }

            /*
             | PRELOADED ONE AT A TIME, BEHIND THE PICTURE THAT IS ON SCREEN.
             |
             | This used to fire all of them at once. MEASURED on the same throttled load: the
             | four requests went out together and picture ONE — the only one anybody is looking
             | at, and the largest at 51 KB — finished LAST at 22.5s, while picture two finished
             | at 19.3s. The preload was starving the visible picture in order to warm up
             | pictures nobody had asked for yet.
             |
             | So picture one is waited for first, and the rest queue single file behind it.
             | ensure() on picture one costs no second request: the banner's own background is
             | already fetching that exact URL, so this joins the cached response.
            */
            function preloadFrom(index) {
                if (index >= images.length) return;

                // Carries on whether or not that one arrived: one broken picture must not stop
                // the rest of the set being warmed up.
                ensure(images[index], function () {
                    preloadFrom(index + 1);
                });
            }

            ensure(images[0], function () {
                preloadFrom(1);
            });

            var banded = getComputedStyle(banner);
            var layer = document.createElement('div');

            layer.style.position = 'absolute';
            layer.style.top = '0';
            layer.style.right = '0';
            layer.style.bottom = '0';
            layer.style.left = '0';
            /*
             | Copied from the banner rather than hardcoded to "cover center". If the two
             | ever disagreed the photograph would visibly jump at the moment the fade
             | finishes and the banner adopts the picture — the crop has to be identical
             | on both layers.
            */
            layer.style.backgroundSize = banded.backgroundSize;
            layer.style.backgroundPosition = banded.backgroundPosition;
            layer.style.backgroundRepeat = 'no-repeat';
            layer.style.opacity = '0';
            // Never intercepts a click. The vehicle picker sits over this.
            layer.style.pointerEvents = 'none';
            layer.style.zIndex = '0';

            /*
             | THE BANNER HAS TO BE THE LAYER'S CONTAINING BLOCK, and this is where that
             | belongs — with the code that creates the layer, not in the markup.
             |
             | The four zero offsets above mean "fill my containing block", which is the
             | nearest POSITIONED ancestor. The banner used to carry an inline
             | position: relative for the dots; when the dots moved into the flow and it was
             | removed, this layer silently started sizing itself against the page instead —
             | so every switch flashed the incoming picture across the whole screen before
             | committing it to the banner at the right size.
            */
            if (banded.position === 'static') {
                banner.style.position = 'relative';
            }

            banner.insertBefore(layer, banner.firstChild);

            /*
             | The layer is positioned, so it would paint over the headings and the picker,
             | which are static. Lifting the banner's own children above it is what keeps
             | the content on top — and z-index only has meaning on a positioned element,
             | hence the relative alongside it. Neither moves anything by a pixel.
            */
            Array.prototype.forEach.call(banner.children, function (child) {
                if (child === layer) return;

                if (getComputedStyle(child).position === 'static') {
                    child.style.position = 'relative';
                }

                child.style.zIndex = '1';
            });

            function commit(src) {
                banner.style.backgroundImage = 'url("' + src + '")';

                // The theme's lazyload sets the background from data-bg and marks the
                // element done. Clearing the attribute stops it painting the first
                // picture back over a later one if it runs again.
                banner.removeAttribute('data-bg');

                // Hidden again with no transition, so resetting it is invisible rather
                // than a second fade running backwards over the new picture.
                layer.style.transition = 'none';
                layer.style.opacity = '0';
            }

            function markDots(index) {
                dots.forEach(function (dot, i) {
                    dot.classList.toggle('is-active', i === index);
                });
            }

            /*
             | The actual switch. Only ever called for a picture ensure() has confirmed the
             | browser holds — show() below is what enforces that.
            */
            function paint(index) {
                var previous = current;

                current = index;
                waiting = false;

                if (fade === 0 || current === previous) {
                    commit(images[current]);

                    return;
                }

                /*
                 | Checked on every switch, not once at startup, because the cost of getting
                 | it wrong is the whole picture flashing across the page: the layer fills its
                 | nearest positioned ancestor, and if anything ever leaves that ancestor being
                 | something other than the banner, this puts it back BEFORE the fade rather
                 | than after it. Two reads of a cached layout value; no work in the common case.
                */
                if (layer.offsetWidth !== banner.offsetWidth || layer.offsetHeight !== banner.offsetHeight) {
                    banner.style.position = 'relative';
                }

                // Staged, and NOT collapsible into one block: the layer has to be
                // transparent and carrying the new picture in one frame, and only then
                // told to become opaque. Setting both in the same frame gives the browser
                // nothing to animate from and the fade is skipped entirely.
                layer.style.transition = 'none';
                layer.style.opacity = '0';
                layer.style.backgroundImage = 'url("' + images[current] + '")';

                // Reading a layout property flushes the two lines above, which is what
                // makes the next frame a real starting point rather than a no-op.
                void layer.offsetHeight;

                layer.style.transition = 'opacity ' + fade + 'ms ease-in-out';
                layer.style.opacity = '1';

                /*
                 | Timed rather than driven by transitionend. If the tab is hidden mid-fade
                 | the event may never arrive, and the banner would then be left showing an
                 | old background under a fully opaque layer — the next fade would appear
                 | to jump. A token makes a stale timeout harmless when a dot is clicked
                 | part-way through.
                */
                var token = ++fadeToken;

                window.setTimeout(function () {
                    if (token === fadeToken) commit(images[current]);
                }, fade);
            }

            /*
             | THE GATE. Everything asks for a picture through here, and nothing gets painted
             | until the browser actually holds it.
            */
            function show(index) {
                var next = ((index % images.length) + images.length) % images.length;
                var src = images[next];
                var token = ++showToken;

                /*
                 | The dot moves at once even when the picture cannot, so a click is never
                 | silently swallowed — the dots say what is coming. On a warm cache, which is
                 | every visit after the first, the dot and the picture move in the same frame
                 | and none of this is visible.
                */
                markDots(next);

                if (ready[src]) {
                    paint(next);

                    return;
                }

                // Not here yet, so the banner keeps showing the picture it already has —
                // rather than going blank while this one downloads.
                waiting = true;

                ensure(src, function (ok) {
                    // A dot clicked, or a tick fired, after this one wins. Without the token a
                    // slow picture landing late would drag the hero back to something nobody
                    // had chosen.
                    if (token !== showToken) return;

                    waiting = false;

                    if (!ok) {
                        // The picture is never arriving. The banner keeps what it has, and the
                        // dots go back to telling the truth about what is on screen.
                        markDots(current);

                        return;
                    }

                    paint(next);
                });
            }

            function start() {
                stop();
                timer = window.setInterval(function () {
                    // A picture is still on its way. Queueing another one on top of it would
                    // mean the rotation runs ahead of what the connection can deliver.
                    if (waiting) return;

                    show(current + 1);
                }, interval);
            }

            function stop() {
                if (timer !== null) {
                    window.clearInterval(timer);
                    timer = null;
                }
            }

            dots.forEach(function (dot) {
                dot.addEventListener('click', function () {
                    show(parseInt(dot.getAttribute('data-hero-page'), 10) || 0);
                    // Restarted, not merely left running: clicking a dot and having it
                    // move on half a second later feels broken.
                    start();
                });
            });

            /*
             | Paused while the tab is hidden. A background tab still fires setInterval,
             | so a shop left open in another tab would spend the afternoon cycling
             | pictures nobody is looking at.
            */
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    stop();
                } else {
                    start();
                }
            });

            start();
        });
    }

    /* ------------------------------------------------------------------ *
     | Filtering without losing your place.
     |
     | Ticking a filter used to submit the form, load a new document and drop
     | the shopper at the top of the page — so choosing a second filter meant
     | scrolling back down to the sidebar every time. On a shop that is the
     | difference between browsing and fighting the page.
     |
     | So the same URL is fetched and only the parts that actually differ are
     | put in place: the result-count bar, the grid, and the pagination. The
     | scroll position is never touched, because nothing above the results
     | moves.
     |
     | NO ELEMENT THE THEME TOUCHED IS EVER REPLACED, and that is deliberate.
     | The sidebar holds a noUiSlider whose initialiser throws if it is run over
     | fresh markup — a trap this project has already paid for once — and the
     | accordion headers are bound directly, not delegated, so a replaced header
     | silently stops opening. So the sidebar is updated one option row at a
     | time: counts and disabled states patched, rows moved, added or removed as
     | the server's options change. Every widget keeps its identity, its state
     | and its handlers.
     |
     | It is still enhancement. The form has a real submit button and a real
     | GET action, the pagination is real links, and any failure — a network
     | error, a response that does not contain the regions — falls through to
     | an ordinary navigation. Nothing here is load-bearing.
     * ------------------------------------------------------------------ */
    function bindSmoothListing() {
        var summary = document.querySelector('[data-listing-summary]');
        var grid = document.querySelector('[data-listing-grid]');

        // Not a listing page. The sort select on any other page keeps navigating.
        if (!summary || !grid) return;

        var filters = document.querySelector('[data-listing-filters]');
        var busy = false;
        var queued = null;

        function fade(on) {
            [summary, grid].forEach(function (el) {
                el.style.transition = 'opacity 150ms linear';
                el.style.opacity = on ? '0.45' : '';
            });
        }

        /* The pagination block is absent entirely on a single-page result, so it cannot
         | just be swapped — it has to be inserted or removed as the result count crosses
         | that line. Getting this wrong strands a shopper on page 1 of 9 with no way on. */
        function syncPagination(doc) {
            var current = document.querySelector('[data-listing-pagination]');
            var fresh = doc.querySelector('[data-listing-pagination]');

            if (fresh && current) {
                current.className = fresh.className;
                current.innerHTML = fresh.innerHTML;
            } else if (fresh && !current) {
                grid.parentNode.insertBefore(fresh.cloneNode(true), grid.nextSibling);
            } else if (!fresh && current) {
                current.remove();
            }
        }

        /*
         | Bring the sidebar's options in line with the new results, ROW BY ROW.
         |
         | Row level rather than group level, and that distinction is the whole design.
         | Replacing a group's markup was the obvious approach and it is wrong twice over:
         | the theme binds its accordion with $('.brator-filter-item-title').on('click'),
         | a direct binding, so a replaced header stops opening and closing; and the price
         | group holds a noUiSlider whose initialiser throws if it is run over fresh markup.
         |
         | Individual option rows, by contrast, have nothing bound to them — checked, and
         | the only handler near one is our own auto-submit on its checkbox. So rows are
         | patched, moved, added and removed, while every element the theme touched keeps
         | its identity. Existing rows are MOVED with appendChild rather than recreated,
         | which reorders them without losing a thing.
         |
         | It has to handle appearing and disappearing options, not just changing numbers:
         | a brand with no matches is not rendered at all, so filtering by origin genuinely
         | shortens the brand list.
        */
        function syncFilterOptions(doc) {
            if (!filters) return;

            var freshFilters = doc.querySelector('[data-listing-filters]');
            if (!freshFilters) return;

            var groups = filters.querySelectorAll('.brator-filter-item-area');
            var freshGroups = freshFilters.querySelectorAll('.brator-filter-item-area');

            // A different number of groups means the pages are not the same shape, and
            // pairing them by index would scramble the sidebar. Left alone instead: the
            // counts go stale, which is survivable, unlike a shuffled filter list.
            if (groups.length !== freshGroups.length) return;

            var key = function (input) {
                return input.name + ' ' + input.value;
            };

            groups.forEach(function (group, index) {
                // Never the price group: that is where the slider lives.
                if (group.querySelector('[data-price-slider]')) return;

                var area = group.querySelector('.brator-filter-item-content-area');
                var freshArea = freshGroups[index].querySelector('.brator-filter-item-content-area');
                if (!area || !freshArea) return;

                var rowOf = function (input) {
                    return input.closest('.brator-filter-item-content');
                };

                var existing = {};
                var seen = [];

                area.querySelectorAll('input[type="checkbox"][name]').forEach(function (input) {
                    var row = rowOf(input);
                    if (row) existing[key(input)] = row;
                });

                freshArea.querySelectorAll('input[type="checkbox"][name]').forEach(function (input) {
                    var id = key(input);
                    var freshRow = rowOf(input);
                    if (!freshRow) return;

                    var row = existing[id];

                    if (row) {
                        var box = row.querySelector('input[type="checkbox"]');
                        var freshBox = freshRow.querySelector('input[type="checkbox"]');
                        var count = row.querySelector('.brator-count');
                        var freshCount = freshRow.querySelector('.brator-count');

                        if (box && freshBox) box.disabled = freshBox.disabled;
                        if (count && freshCount) count.textContent = freshCount.textContent;

                        // Moves the node into place. Its handlers, and the checkbox's own
                        // state, come with it.
                        area.appendChild(row);
                    } else {
                        area.appendChild(freshRow.cloneNode(true));
                    }

                    seen.push(id);
                });

                // Whatever the server no longer offers. Safe to drop: an option the shopper
                // has ticked is always rendered, so a checked row is never in here.
                area.querySelectorAll('input[type="checkbox"][name]').forEach(function (input) {
                    if (seen.indexOf(key(input)) === -1) {
                        var row = rowOf(input);
                        if (row) row.remove();
                    }
                });

                /*
                 | Radios (the rating filter) keep their rows — a rating is never absent from
                 | the list, unlike a brand — but their COUNTS go stale otherwise, and a count
                 | that contradicts the result it produces is the bug this project has spent
                 | the most time removing.
                */
                area.querySelectorAll('input[type="radio"][name]').forEach(function (input) {
                    var freshInput = freshArea.querySelector(
                        'input[type="radio"][name="' + input.name + '"][value="' + input.value + '"]');
                    if (!freshInput) return;

                    var row = rowOf(input);
                    var freshRow = rowOf(freshInput);
                    var count = row && row.querySelector('.brator-count');
                    var freshCount = freshRow && freshRow.querySelector('.brator-count');

                    if (count && freshCount) count.textContent = freshCount.textContent;
                });
            });

            /*
             | The "Clear all filters" row, which appears and disappears with hasAnyNarrowing()
             | and whose label changes when a car is selected.
             |
             | It is not a checkbox row, so the loop above never touched it. That was invisible
             | while the price inputs kept hasAnyNarrowing() permanently true — the row was
             | simply always there. With the price trap fixed, the row genuinely toggles, and
             | without this it would only ever appear after a full page load: apply a filter in
             | place and there would be no way to clear it.
            */
            var freshClear = freshFilters.querySelector('[data-clear-filters-row]');
            var liveClear = filters.querySelector('[data-clear-filters-row]');

            if (freshClear && liveClear) {
                // Still needed, but the label may have changed ("…, including your car").
                liveClear.innerHTML = freshClear.innerHTML;
            } else if (freshClear && !liveClear) {
                // Find which group it belongs to in the fresh document, and put it in the same
                // group here — by index, which is safe because the group count was checked.
                for (var g = 0; g < freshGroups.length; g++) {
                    if (!freshGroups[g].contains(freshClear)) continue;

                    var host = groups[g].querySelector('.brator-filter-item-content-area');
                    if (host) host.appendChild(freshClear.cloneNode(true));
                    break;
                }
            } else if (!freshClear && liveClear) {
                liveClear.remove();
            }

            /*
             | Re-apply whatever is typed in the brand search box. Rows that just arrived
             | know nothing about it, and would otherwise show up in a list the shopper has
             | narrowed to one word.
            */
            filters.querySelectorAll('[data-filter-input]').forEach(function (input) {
                if (input.value.trim() !== '') {
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        }

        function apply(url, push) {
            /*
             | A request is already in flight, so this one is remembered and run when that
             | finishes — only the LATEST, because the intermediate states of somebody
             | ticking three boxes are of no interest and fetching each in turn would only
             | make it slower. Returning true matters: the caller must still swallow the
             | click, or ticking a second box quickly would trigger exactly the full reload
             | this function exists to avoid.
            */
            if (busy) {
                queued = { url: url, push: push };

                return true;
            }

            busy = true;
            fade(true);

            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('status ' + response.status);

                    return response.text();
                })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var freshSummary = doc.querySelector('[data-listing-summary]');
                    var freshGrid = doc.querySelector('[data-listing-grid]');

                    if (!freshSummary || !freshGrid) throw new Error('no listing in response');

                    summary.innerHTML = freshSummary.innerHTML;

                    // className too, not only the contents: switching between the grid and
                    // list views renders a different template whose wrapper carries
                    // "type-list", and the layout comes from that class.
                    grid.className = freshGrid.className;
                    grid.innerHTML = freshGrid.innerHTML;

                    syncPagination(doc);
                    syncFilterOptions(doc);

                    if (doc.title) document.title = doc.title;

                    if (push) window.history.pushState({ listing: true }, '', url);

                    /*
                     | The theme's lazysizes watches the DOM with a MutationObserver, so the
                     | product images that just arrived load themselves — nothing to re-run.
                     | Our own bindings are not observers, so the new sort select and any new
                     | filter controls do need binding.
                    */
                    bindAutoSubmit();
                    bindQuantitySteppers();

                    busy = false;

                    if (queued !== null) {
                        var next = queued;
                        queued = null;
                        apply(next.url, next.push);

                        return;
                    }

                    fade(false);
                })
                .catch(function () {
                    // Whatever went wrong, the shopper still gets their results — just with
                    // the reload they would have had before any of this existed.
                    window.location = url;
                });

            return true;
        }

        // The filter form: intercepted rather than submitted. Its GET action and submit
        // button are untouched, so with JavaScript off this is a normal form.
        if (filters) {
            filters.addEventListener('submit', function (event) {
                /*
                 | Empty fields are dropped rather than submitted as `price_min=`.
                 |
                 | The server reads an empty value as "no filter" either way, so this is not a
                 | correctness fix — it is an honesty one. This form's whole design is that the
                 | URL describes exactly what is on screen, and ?price_min=&price_max= on a page
                 | with no price filter is a URL that describes something else.
                */
                var params = new URLSearchParams();

                new FormData(filters).forEach(function (value, key) {
                    if (String(value) !== '') params.append(key, value);
                });

                var query = params.toString();
                var url = window.location.pathname + (query ? '?' + query : '');

                if (apply(url, true)) event.preventDefault();
            });
        }

        /*
         | Per-page, the view toggle and the pagination are real links, and they are only
         | intercepted inside the two regions this code owns. A link anywhere else on the
         | page — a product, a category, the header — is left completely alone.
        */
        document.addEventListener('click', function (event) {
            var link = event.target.closest ? event.target.closest('a[href]') : null;

            if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
            if (link.target === '_blank') return;
            if (!link.closest('[data-listing-summary], [data-listing-pagination]')) return;

            var here = new URL(link.href, window.location.href);

            // Same document, different query. Anything that leaves this page must not be
            // swallowed — otherwise a stray link in the toolbar would silently do nothing.
            if (here.origin !== window.location.origin || here.pathname !== window.location.pathname) return;

            if (apply(here.pathname + here.search, true)) event.preventDefault();
        });

        // Back and forward still work: the same swap, without pushing a new entry.
        window.addEventListener('popstate', function () {
            apply(window.location.pathname + window.location.search, false);
        });

        listingNavigate = function (url) {
            var here = new URL(url, window.location.href);

            if (here.origin !== window.location.origin || here.pathname !== window.location.pathname) {
                return false;
            }

            return apply(here.pathname + here.search, true);
        };
    }

    /* ------------------------------------------------------------------ *
     | The vehicle picker: advance the cascade without reloading the page.
     |
     | Choosing a Year posted the form, the server answered back(), and the
     | whole document reloaded — which scrolled the shopper to the top of the
     | homepage. Five times over, once per level, so narrowing down to an engine
     | meant scrolling back to the picker after every single choice.
     |
     | The cascade is STILL decided by the server. The same form is posted, and
     | the picker is taken out of the response — so every rule about which
     | options exist at which level stays in one place, and none of it is
     | duplicated here. Only the delivery changed.
     |
     | WHAT IS COPIED IS THE OPTIONS, NOT THE SELECTS. The theme runs select2
     | over these dropdowns, which replaces the native control with its own DOM;
     | swapping a select element would strand that widget on a node no longer in
     | the document. So each existing select keeps its identity and only its
     | <option> children change, with select2 taken down and put back around the
     | swap. The theme initialises with $('.brator-select-active').select2() and
     | no arguments, so re-initialising reproduces exactly the same widget —
     | which is the only reason this is safe to do.
     |
     | Choosing an Engine is left alone entirely: that is not a cascade step, it
     | is the shopper saying "this is my car, show me parts", and it should go to
     | the results the way it always did.
     * ------------------------------------------------------------------ */
    function bindVehicleCascade() {
        /*
         | EVERY picker on the page is refreshed, not just the one that was used. A shop page
         | carries two — one in the header, one in the body — and they all read the same
         | session, so leaving the others showing the old state would mean the same form
         | disagreeing with itself in two places on one screen.
        */
        function refresh(doc) {
            var fresh = doc.querySelector('[data-vehicle-picker]');
            if (!fresh) throw new Error('no picker in the response');

            document.querySelectorAll('[data-vehicle-picker]').forEach(function (picker) {
                picker.querySelectorAll('select[name]').forEach(function (select) {
                    var replacement = fresh.querySelector(
                        'select[name="' + CSS.escape(select.name) + '"]'
                    );

                    if (!replacement) return;

                    var $select = window.jQuery ? window.jQuery(select) : null;
                    var enhanced = !!($select && $select.data('select2'));

                    if (enhanced) $select.select2('destroy');

                    select.innerHTML = replacement.innerHTML;
                    // Set BEFORE select2 comes back: it renders the disabled look at
                    // initialisation, so a select disabled afterwards would still look
                    // usable and then refuse to open.
                    select.disabled = replacement.disabled;

                    if (enhanced) $select.select2();
                });
            });

            /*
             | "Start again" appears the moment there is anything to clear. It is a button
             | inside the form, so it is not carried by the <option> copying above — only its
             | d-none is synced, from the same response. Copying the class rather than
             | toggling it keeps the server the single judge of when it applies.
            */
            var freshReset = fresh.querySelector('[data-vehicle-reset]');

            if (freshReset) {
                document.querySelectorAll('[data-vehicle-reset]').forEach(function (reset) {
                    reset.className = freshReset.className;
                });
            }

            // The "no vehicles for that year" note also depends on how far the cascade has
            // got, so it moves with it.
            var freshExtras = doc.querySelector('[data-vehicle-extras]');

            if (freshExtras) {
                document.querySelectorAll('[data-vehicle-extras]').forEach(function (extras) {
                    extras.innerHTML = freshExtras.innerHTML;
                });
            }
        }

        document.querySelectorAll('[data-vehicle-picker]').forEach(function (form) {
            if (form.dataset.cascadeBound) return;
            form.dataset.cascadeBound = '1';

            var busy = false;

            form.addEventListener('submit', function (event) {
                /*
                 | An Engine has been chosen, so this is "show me parts for this car" and
                 | the server will redirect to the shop. Left to navigate normally — the
                 | shopper is asking to go somewhere.
                */
                var engine = form.querySelector('[name="vehicle_variant_id"]');
                if (engine && engine.value) return;

                // A click on Search rather than a dropdown changing. Also a deliberate
                // "go", so it is not swallowed either.
                if (event.submitter) return;

                if (busy) return;

                event.preventDefault();
                busy = true;

                var body = new FormData(form);

                fetch(form.action, {
                    method: 'POST',
                    body: body,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('status ' + response.status);

                        return response.text();
                    })
                    .then(function (html) {
                        refresh(new DOMParser().parseFromString(html, 'text/html'));
                        busy = false;
                    })
                    .catch(function () {
                        /*
                         | Falls back to the reload this replaced, so a shopper still gets
                         | their next dropdown. form.submit() rather than requestSubmit()
                         | on purpose: requestSubmit fires the submit event again and this
                         | handler would swallow it, leaving the picker permanently stuck.
                        */
                        form.submit();
                    });
            });
        });
    }

    /* ------------------------------------------------------------------ *
     | One order per click.
     |
     | Nothing stopped a shopper pressing "Place order" twice. POST /checkout has no
     | throttle and no idempotency token, and PlaceReceiptAction loads the basket lines
     | and runs its empty-basket guard OUTSIDE the transaction without locking them — so
     | two requests that arrive together both pass that guard and both go on to write a
     | receipt. Two receipts is stock decremented twice, two confirmation emails, and a
     | coupon counted twice against its usage.
     |
     | THIS IS A CLIENT-SIDE GUARD ONLY, AND IT DOES NOT FIX THE DOUBLE-RECEIPT BUG.
     | Read that sentence before treating this as done. All it removes is the ACCIDENT —
     | the shopper who cannot tell whether their click registered and helps it along with
     | a second one. Everything the server would need is still missing: no idempotency
     | key on the checkout request, no lock on the basket lines inside the transaction,
     | and no rate limit on POST /checkout (routes/web.php declares it bare). A second
     | post that never goes through this handler — a script, a replayed request, the same
     | cart open in two tabs — still writes two receipts, decrements stock twice and
     | sends two emails. That fix is a separate, larger piece of work.
     |
     | What this DOES buy: the form will not submit a second time from this page, and the
     | button says what it is doing so nobody feels the need to press it again.
     |
     | THE FIRST SUBMIT IS NEVER SWALLOWED — the order still has to be placed.
     |
     | WHEN THE BUTTON IS DISABLED, and why that is two cases rather than one.
     |
     | A disabled control's name and value are left out of the submitted body. The checkout
     | button carries neither — checked, it is a bare <button type="submit"> — so disabling
     | it in the submit handler drops nothing, and the handler is the right place: the
     | browser has already built the entry list by the time submit fires.
     |
     | A NAMED button is one attribute away on this very page: the cart's +/- controls are
     | real submits posting name="step". The entry list is built first there too, so in a
     | spec-following engine disabling it synchronously is still safe — but this is exactly
     | the kind of edge older engines have got wrong, and the cost of being wrong is a
     | checkout that posts without the field the server branches on. So a named button is
     | disabled from a zero-delay timeout instead: after the submission has left, never
     | during it. It still gets its busy label, which is the whole point of the guard.
     |
     | AND THE BACK BUTTON, which is not optional. Check out, press Back, and the
     | browser hands over this page from its back/forward cache exactly as it was left:
     | button disabled, reading "Placing your order…", this closure still believing a
     | submit is in flight. Without re-enabling on pageshow, a shopper who goes back to
     | change a line finds a dead button and no way to order at all. A server-side
     | validation failure needs nothing — that comes back as a fresh document.
     * ------------------------------------------------------------------ */
    function bindSubmitOnce() {
        document.querySelectorAll('[data-submit-once]').forEach(function (form) {
            if (form.dataset.submitOnceBound) return;
            form.dataset.submitOnceBound = '1';

            var button = form.querySelector('button[type="submit"], button:not([type])');
            var busyLabel = form.getAttribute('data-submit-once-label') || '';
            var submitting = false;

            /*
             | The label to restore. Captured at SUBMIT time rather than here, and that changed
             | when the cart's in-place update arrived: the "Place order — 1.459,85 ден" button
             | now has its total patched as the basket changes, so a copy taken at bind time goes
             | stale, and a shopper who checks out and presses Back would be shown a total from
             | several quantity changes ago. It still must not be read back off the button when
             | restoring, because by then it says "Placing your order…" — so it is read once, in
             | the submit handler, before markBusy() overwrites anything.
             |
             | innerHTML rather than text, so a button holding an icon or a <span> comes back whole.
            */
            var original = button ? button.innerHTML : '';

            function release() {
                submitting = false;

                if (!button) return;

                button.disabled = false;
                button.innerHTML = original;
            }

            function markBusy() {
                // The named-button path defers this, so a pageshow can have released the guard
                // in between. Re-disabling after that would hand the shopper the dead button
                // release() exists to prevent — with no second submit left to re-enable it.
                if (!submitting) return;

                button.disabled = true;

                // textContent, not innerHTML: the label is plain text from an attribute and
                // must never be parsed as markup.
                if (busyLabel !== '') button.textContent = busyLabel;
            }

            form.addEventListener('submit', function (event) {
                if (submitting) {
                    event.preventDefault();

                    return;
                }

                submitting = true;

                if (!button) return;

                // Read before anything overwrites it. See the note where `original` is declared.
                original = button.innerHTML;

                // See above: a named button is disabled only once the submission is on its
                // way, so its name/value cannot be dropped from the body.
                if (button.name) {
                    window.setTimeout(markBusy, 0);
                } else {
                    markBusy();
                }
            });

            /*
             | Unconditional rather than only when event.persisted is true. A restore that
             | did not come from the back/forward cache re-parses the document, so this runs
             | against a button nothing has touched yet and does nothing at all — and that
             | is cheaper than being wrong about which restores kept the DOM.
            */
            window.addEventListener('pageshow', release);
        });
    }

    /* ------------------------------------------------------------------ *
     | The header's mini-cart panel: the parts of it that are shared.
     |
     | Called after any basket change, wherever it happened, so the panel and the badge stop
     | needing a page load to tell the truth. They are fed by a view composer and nothing else,
     | so before this every change reached them only via a new document.
     |
     | The PANEL ELEMENT ITSELF IS NEVER REPLACED — only its contents. It carries the
     | .mini-cart-open class and the 0.3s transition, so swapping the element would slam an open
     | panel shut mid-hover. The close button inside it IS replaced, which is why bindMiniCart
     | closes by delegation rather than leaning on the theme's direct $('.brator-cart-close')
     | binding: a replaced button would silently stop closing.
     * ------------------------------------------------------------------ */
    function refreshMiniCart(doc) {
        var regions = [
            ['[data-mini-cart]', 'html'],
            ['[data-mini-cart-badge]', 'text'],
            // Only the shop header carries this one; absent on the homepage header.
            ['[data-mini-cart-total]', 'text'],
        ];

        regions.forEach(function (region) {
            var live = document.querySelector(region[0]);
            var fresh = doc.querySelector(region[0]);

            if (!live || !fresh) return;

            if (region[1] === 'text') {
                live.textContent = fresh.textContent;

                return;
            }

            /*
             | The pictures that just arrived are <img class="lazyload" data-src>, and the
             | theme's lazysizes watches the DOM with a MutationObserver, so they load
             | themselves. Nothing to re-run here.
            */
            live.innerHTML = fresh.innerHTML;
        });
    }

    /* ------------------------------------------------------------------ *
     | The mini-cart: opens on a click, closes on one, and is usable in between.
     |
     | WHAT IT DID BEFORE ANY OF THIS. The theme adds .mini-cart-open on a click on
     | `.brator-cart-link a` and never calls preventDefault — so the panel appeared and the same
     | click replaced the document with /cart. Measured: opacity 1, then gone a frame or two
     | later. Stefan reported it as "shows for a split second and directly redirects", which is
     | exactly what it did. (He described it as a hover panel; there is no :hover rule touching
     | .brator-cart-item-list in any of the theme's twelve stylesheets and no mouseenter handler
     | in its JavaScript. It never was one.)
     |
     | AND THEN IT WAS A HOVER PANEL FOR A DAY, WHICH WAS WORSE. I built it that way and Stefan
     | asked for it removed, correctly. A panel that unfurls over the page because your pointer
     | crossed the header on its way somewhere else is something the shop does TO you; the cart
     | icon sits next to the search box and the vehicle picker, so it opened constantly on the
     | way past. Hover also needed a whole apparatus to survive itself — a 400ms grace period so
     | it did not run away from the pointer, a separate timer so a phantom mouseenter could not
     | cut a confirmation short, a flag tracking our own notion of open because the theme wrote
     | its class behind our back, and focusin/focusout to keep the keyboard from closing it. All
     | of that is gone with the hover it existed to prop up.
     |
     | ONE BEHAVIOUR, NOT TWO. A touch screen has no hover at all, so the tap was always the
     | real way in and hover was a second path that only some visitors ever saw. Now everybody
     | gets the same panel, opened the same way, and it stays until it is dismissed rather than
     | until a pointer wanders off.
     |
     | FOUR WAYS OUT, all of them somebody asking: the icon again, the panel's own ×, Escape, or
     | a click anywhere else on the page.
     |
     | THE ANCHOR STAYS AN ANCHOR. The click is swallowed only inside this handler, which only
     | exists once this file has run — so with JavaScript off `<a href="/cart">` still goes to
     | the cart, exactly as it always did.
     |
     | NO NEW CSS CLASS. .mini-cart-open is the theme's own class, already defined in
     | theme-style.css, and it is toggled here at runtime — so ThemeFidelityTest, which reads
     | classes out of the SERVER-RENDERED markup, has nothing to object to either way.
     * ------------------------------------------------------------------ */
    function bindMiniCart() {
        document.querySelectorAll('[data-mini-cart-toggle]').forEach(function (toggle) {
            var wrapper = toggle.closest('.brator-cart-link');
            var panel = wrapper && wrapper.querySelector('[data-mini-cart]');

            if (!wrapper || !panel) return;
            if (wrapper.dataset.miniCartBound) return;
            wrapper.dataset.miniCartBound = '1';

            /*
             | OUR OWN NOTION OF OPEN, RATHER THAN READING THE CLASS BACK OFF THE PANEL.
             |
             | Measured the hard way: an earlier toggle asked `panel.classList.contains(...)` and
             | the icon click never opened anything. The theme's own click handler is bound first
             | (brator-script.js is a blocking script; this file is deferred) and it adds
             | .mini-cart-open on the way past — so by the time our handler ran, the panel always
             | looked ALREADY OPEN and the toggle always chose to close it.
             |
             | A flag we own cannot be written behind our back. This is why the toggle below is
             | safe now that hover is gone: `isOpen` is only ever changed by open() and close().
            */
            var isOpen = false;

            /*
             | The theme's click handler also does `$('body').addClass('rtl')`, which is nonsense
             | on a left-to-right shop — it is a leftover from the demo's direction switcher.
             | It is inert today because the layout ships the rtl.css link commented out, so
             | nothing styles that class. Reversed anyway: the panel is ours to open now, and
             | leaving a class on <body> that says the page is right-to-left is a trap for
             | whoever loads that stylesheet next.
            */
            function undoThemeSideEffect() {
                document.body.classList.remove('rtl');
            }

            function open() {
                isOpen = true;
                panel.classList.add('mini-cart-open');
            }

            function close() {
                isOpen = false;
                panel.classList.remove('mini-cart-open');
                undoThemeSideEffect();
            }

            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                undoThemeSideEffect();

                if (isOpen) {
                    close();

                    return;
                }

                open();
            });

            /*
             | A click anywhere else closes it — the same gesture as clicking off any other menu,
             | and on a phone the obvious one, where the alternative is finding a small ×.
             |
             | Bound on the document, so a click on the panel's own contents must be excluded
             | explicitly: `wrapper` contains both the icon and the panel, and pressing a remove
             | button inside the panel would otherwise shut it the moment it did its job.
            */
            document.addEventListener('click', function (event) {
                if (!isOpen) return;
                if (wrapper.contains(event.target)) return;

                close();
            });

            /*
             | Delegated on the wrapper rather than bound to the button, because the panel's
             | contents are replaced whenever the basket changes and a directly-bound close
             | button would stop working after the first removal — the exact trap the theme's
             | own $('.brator-cart-close').on('click') falls into.
            */
            wrapper.addEventListener('click', function (event) {
                var closer = event.target.closest ? event.target.closest('.brator-cart-close') : null;

                if (!closer) return;

                event.preventDefault();
                close();
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' || event.key === 'Esc') close();
            });
        });
    }

    /* ------------------------------------------------------------------ *
     | Changing the basket without losing the page.
     |
     | THE BUG THIS EXISTS FOR. Every action on /cart — plus, minus, Enter, remove a line, apply
     | a code, remove a code — was a form post ending in a redirect back to /cart. Measured, with
     | all five checkout fields filled first: a new document every time, the scroll position
     | thrown from 1676 to 0, and all five fields empty. Stefan's words were "every time you do
     | something the form refreshes and you need to start from the beginning".
     |
     | It cannot be fixed on the server. The fields are old(), old() is repopulated only from
     | input flashed by a validation failure on the SAME request, and a quantity post does not
     | carry the checkout fields at all — so ->withInput() would have nothing to flash. The
     | reload itself had to go.
     |
     | STILL ENHANCEMENT, all of it. Every form here keeps its real method, action and submit
     | button; any failure falls through to an ordinary submit; and with JavaScript off the page
     | behaves exactly as it did before this function was written.
     |
     | THREE RULES, and they are the specification rather than implementation detail:
     |
     |  1. ABSOLUTE QUANTITIES, NEVER A STEP. The +/- buttons post name="step" and the server
     |     adds it to the posted quantity. Sent as-is through a queue, two fast clicks could be
     |     coalesced or reordered and a click would vanish. So the step is resolved against the
     |     input HERE and an absolute number is posted, with `step` deleted from the body. The
     |     input is also updated immediately, so a second click computes from what the shopper
     |     can see, and the worst case of a late response is a number flickering to the right
     |     value rather than a click going missing.
     |
     |  2. THE OPTIONAL BLOCKS ARE INSERTED AND REMOVED, not swapped. The order summary and the
     |     whole checkout block only exist while the basket has something in it, and the discount
     |     row inside the summary only exists while a code is discounting something. Assuming
     |     they are present is how you strand somebody on a cart with no checkout form.
     |
     |  3. THE CHECKOUT BLOCK IS NEVER RE-RENDERED WHILE IT IS ON THE PAGE. It holds what the
     |     shopper has typed — the entire point of this work — and it holds the data-submit-once
     |     binding that stops a double-click placing two orders. Only the total on the button and
     |     the validation list inside it are patched.
     * ------------------------------------------------------------------ */
    function bindBasketForms() {
        if (document.documentElement.dataset.basketFormsBound) return;
        document.documentElement.dataset.basketFormsBound = '1';

        // Null everywhere except /cart. The mini-cart's own remove button uses this same
        // machinery from every other page, and there it only refreshes the panel.
        var cart = document.querySelector('[data-cart-region]');
        var chain = Promise.resolve();
        var pending = 0;

        function fade(on) {
            if (!cart) return;

            // The checkout block is deliberately NOT dimmed: dimming a field somebody is typing
            // into, several times while they type, is worse than not acknowledging the request.
            ['[data-cart-lines]', '[data-cart-summary]'].forEach(function (selector) {
                var region = cart.querySelector(selector);

                if (!region) return;

                region.style.transition = 'opacity 150ms linear';
                region.style.opacity = on ? '0.45' : '';
            });
        }

        /* Rule 1. Returns the absolute quantity to post, or null if this is not a quantity form. */
        function absoluteQuantity(form, submitter) {
            var input = form.querySelector('[data-cart-qty]');

            if (!input) return null;

            var step = (submitter && submitter.name === 'step') ? (parseInt(submitter.value, 10) || 0) : 0;
            var min = input.min === '' ? 0 : parseInt(input.min, 10);
            var max = input.max === '' ? 99 : parseInt(input.max, 10);
            var next = (parseInt(input.value, 10) || 0) + step;

            next = Math.min(max, Math.max(min, next));
            input.value = next;

            return next;
        }

        function swap(doc, selector) {
            var live = cart.querySelector(selector);
            var fresh = doc.querySelector(selector);

            if (live && fresh) live.innerHTML = fresh.innerHTML;
        }

        /* Rule 2. */
        function insertOrRemove(doc, selector) {
            var live = cart.querySelector(selector);
            var fresh = doc.querySelector(selector);
            var tail = cart.querySelector('[data-cart-tail]');

            if (fresh && live) {
                live.innerHTML = fresh.innerHTML;
            } else if (fresh && !live && tail) {
                tail.parentNode.insertBefore(document.importNode(fresh, true), tail);
            } else if (!fresh && live) {
                live.remove();
            }
        }

        /* Rule 3. */
        function syncCheckout(doc) {
            var live = cart.querySelector('[data-cart-checkout]');
            var fresh = doc.querySelector('[data-cart-checkout]');
            var tail = cart.querySelector('[data-cart-tail]');

            if (fresh && !live) {
                if (tail) tail.parentNode.insertBefore(document.importNode(fresh, true), tail);

                return;
            }

            if (!fresh && live) {
                live.remove();

                return;
            }

            if (!fresh || !live) return;

            // On the page in both: left alone but for the two things that really changed.
            var liveTotal = live.querySelector('[data-cart-total-label]');
            var freshTotal = fresh.querySelector('[data-cart-total-label]');

            if (liveTotal && freshTotal) liveTotal.textContent = freshTotal.textContent;

            var liveErrors = live.querySelector('[data-cart-checkout-errors]');
            var freshErrors = fresh.querySelector('[data-cart-checkout-errors]');

            if (liveErrors && freshErrors) liveErrors.innerHTML = freshErrors.innerHTML;
        }

        /*
         | The hidden form the "Remove" button targets by id. It lives outside the cart column
         | (a <form> inside a <form> is invalid and the browser drops the inner one), and it
         | exists only while a code is applied — so it is inserted and removed too. A form
         | referenced by the `form` attribute works from anywhere in the document.
        */
        function syncCouponRemoveForm(doc) {
            var live = document.getElementById('remove-coupon');
            var fresh = doc.getElementById('remove-coupon');

            if (fresh && !live) {
                document.body.appendChild(document.importNode(fresh, true));
            } else if (!fresh && live) {
                live.remove();
            }
        }

        function apply(doc) {
            // Every page: the header panel and the badge.
            refreshMiniCart(doc);

            if (!cart) return;

            swap(doc, '[data-cart-flash]');
            swap(doc, '[data-cart-lines]');
            insertOrRemove(doc, '[data-cart-summary]');
            syncCheckout(doc);
            swap(doc, '[data-cart-coupon]');
            syncCouponRemoveForm(doc);

            /*
             | The coupon block was just replaced, so the live-check input in it is a new
             | element with no binding; and a checkout block that was just INSERTED has no
             | double-submit guard yet. Both of these skip anything already bound.
            */
            bindCouponCheck();
            bindSubmitOnce();
        }

        /*
         | Falls back to the submit this replaced, so the shopper's action still happens.
         |
         | form.submit() rather than requestSubmit(): requestSubmit would fire the submit event
         | again and the handler below would swallow it, leaving the control permanently dead.
         | submit() also activates NO button, which is exactly right here — `step` is therefore
         | absent and the absolute quantity already written into the input is what the server
         | applies, so the fallback lands on the same number the in-place path would have.
        */
        function fallback(form, absolute) {
            if (absolute !== null) {
                var input = form.querySelector('[data-cart-qty]');

                if (input) input.value = absolute;
            }

            // form.submit() fires no submit event, so the scroll listener will not see this one.
            // Recorded here instead, or the fallback would land the shopper at the top of the
            // page — the exact thing this whole change is about.
            rememberScrollPosition();

            form.submit();
        }

        document.addEventListener('submit', function (event) {
            var form = event.target;

            if (!form || typeof form.matches !== 'function') return;
            if (!form.matches('[data-basket-form]')) return;
            if (event.defaultPrevented) return;

            var absolute = form.matches('[data-cart-qty-form]')
                ? absoluteQuantity(form, event.submitter)
                : null;

            var body = new FormData(form);

            if (absolute !== null) {
                body.delete('step');
                body.set('quantity', String(absolute));
            }

            event.preventDefault();

            pending++;
            fade(true);

            /*
             | Chained rather than coalesced. The listing's in-place update keeps only the latest
             | request because ticking three filters in a row has two states nobody cares about;
             | a basket is the opposite — every click is a change somebody asked for, and
             | dropping one silently loses money in the shopper's favour or against it.
             |
             | @method('DELETE') travels in the body as _method, so every one of these is a POST
             | as far as fetch is concerned, and Laravel unpacks it exactly as it does for a
             | normal form.
            */
            chain = chain
                .then(function () {
                    return fetch(form.action, {
                        method: 'POST',
                        body: body,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                })
                .then(function (response) {
                    // The server answers these with a redirect to /cart; fetch follows it, so
                    // this is the cart document with the flash message already in it.
                    if (!response.ok) throw new Error('status ' + response.status);

                    return response.text();
                })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');

                    apply(doc);

                    /*
                     | An ADD, so the shopper is still standing on a product or a listing page and
                     | needs telling what just happened. The sentence is the server's own flash —
                     | "{product} — {price} added to your cart." — lifted out of the cart document
                     | the redirect handed us, so the wording and the money formatting live in one
                     | place rather than being reassembled here.
                     |
                     | THE PANEL IS NOT OPENED. It used to be, and the confirmation went inside it;
                     | see showBasketToast() for why that was wrong — the message was correct and
                     | 2,500px above the top of the window. The panel is now only ever opened by
                     | somebody clicking the cart icon, so adding a part does not throw a menu over
                     | the listing they are reading. The badge and the panel's contents are still
                     | brought up to date by apply(), so it tells the truth whenever it is opened.
                     |
                     | If there is no sentence the response was not the cart — which means the add
                     | was refused, and the only route to that is a product going out of stock
                     | between the page loading and the click, since the button is rendered
                     | disabled otherwise. Then nothing is claimed: showBasketToast ignores an
                     | empty message. Saying nothing is worse than a confirmation and far better
                     | than confirming something that did not happen.
                    */
                    if (form.matches('[data-basket-add]')) {
                        var flash = doc.querySelector('[data-cart-flash]');

                        showBasketToast(flash ? flash.textContent.replace(/\s+/g, ' ').trim() : '');
                    }

                    pending--;
                    if (pending === 0) fade(false);
                })
                .catch(function () {
                    pending--;
                    fallback(form, absolute);
                });
        });
    }

    /* ------------------------------------------------------------------ *
     | The discount code field, answering while it is typed.
     |
     | Before: a wrong code was a full page load. Measured — scroll thrown from 1676 to 0, the
     | typed code cleared out of the field, and all five checkout fields emptied, for one typo.
     |
     | WHAT THIS ENDPOINT MAY AND MAY NOT SAY is the important part, and it is enforced on the
     | server (see BasketController::checkCoupon). It returns ONE answer for "no such code" and
     | "that code is switched off", so it cannot be used to find retired codes. Read the note in
     | the controller before changing the shape of this: it records the condition under which
     | the whole feature is safe.
     |
     | A FAILED CHECK MUST NEVER READ AS AN INVALID CODE. Same trap as the admin's vehicle
     | cascade, where an empty dropdown reads as "no vehicles match" when it means "the request
     | failed": here, saying "not valid" because the network hiccuped would talk a shopper out of
     | a code that works. On any failure — including a rate limit — this says nothing at all and
     | leaves the Apply button as the authority.
     * ------------------------------------------------------------------ */
    function bindCouponCheck() {
        document.querySelectorAll('[data-coupon-check]').forEach(function (input) {
            if (input.dataset.couponCheckBound) return;
            input.dataset.couponCheckBound = '1';

            var endpoint = input.getAttribute('data-coupon-check');
            var scope = input.closest('[data-cart-coupon]') || document;
            var message = scope.querySelector('[data-coupon-message]');

            if (!endpoint || !message) return;

            var timer = null;
            var latest = 0;

            /*
             | A tick or a cross as a text character, and the theme's own <p> to put it in. An
             | icon would mean either a new class or a new asset; this needs neither, and the
             | glyph inherits the paragraph's colour and size for free.
            */
            function say(text, ok) {
                if (!text) {
                    message.textContent = '';
                    message.style.display = 'none';

                    return;
                }

                message.textContent = (ok ? '✓ ' : '✗ ') + text;
                message.style.display = '';
            }

            function check() {
                var code = input.value.trim();

                /*
                 | TWO CHARACTERS BEFORE THE SERVER IS ASKED ANYTHING.
                 |
                 | Stefan's example was literally typing "GH" and seeing the cross, and two is
                 | what he asked for over my suggestion of four. It is defensible here for a
                 | specific reason rather than as a general rule: every usable code is already
                 | printed in the homepage top bar, so this endpoint can only ever confirm what
                 | is public, and it cannot tell a retired code from a typo. The throttle on the
                 | route is what keeps it from being a cheap way to walk the code space.
                */
                if (code.length < 2) {
                    say('', false);

                    return;
                }

                var mine = ++latest;

                fetch(endpoint + '?code=' + encodeURIComponent(code), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('status ' + response.status);

                        return response.json();
                    })
                    .then(function (result) {
                        // A later keystroke has already been answered; this one is history.
                        if (mine !== latest) return;

                        /*
                         | `known` is true only for a code that exists AND is switched on, and it
                         | is what decides whether a cross is shown — a real code that has not
                         | reached its minimum spend is not wrong, it is not ready, and marking it
                         | with a cross would talk somebody out of a code they can use by adding
                         | one more part. The wording comes from the server so that it always
                         | matches what pressing Apply would say.
                        */
                        say(result.message || '', !!result.ok || !!result.known);
                    })
                    .catch(function () {
                        if (mine !== latest) return;

                        say('', false);
                    });
            }

            input.addEventListener('input', function () {
                if (timer !== null) window.clearTimeout(timer);

                // 300ms: long enough that typing a ten-character code is one request rather
                // than ten, short enough to feel like the field is answering.
                timer = window.setTimeout(check, 300);
            });
        });
    }

    /* ------------------------------------------------------------------ *
     | Keeping your place across the reloads that are left.
     |
     | The cart's actions no longer reload, but two paths still do: no JavaScript at all, and an
     | in-place update that failed and fell back to a real submit. Both used to land the shopper
     | at the top of the page — measured, scroll 1676 → 0 — which on a long cart means scrolling
     | back down to find what you were doing.
     |
     | sessionStorage rather than the browser's own scroll restoration, which does not apply
     | across a POST → redirect → GET.
     * ------------------------------------------------------------------ */
    function bindScrollMemory() {
        var scope = document.querySelector('[data-scroll-memory]');

        if (!scope) return;
        if (scope.dataset.scrollMemoryBound) return;
        scope.dataset.scrollMemoryBound = '1';

        var key = 'brator:scroll:' + window.location.pathname;

        function store() {
            try {
                window.sessionStorage.setItem(key, String(Math.round(window.pageYOffset)));
            } catch (error) {
                // Private mode, or a full quota. Losing the scroll position is not worth an
                // exception that would take the rest of init() with it.
            }
        }

        // Restored and then consumed, so a later plain visit to the cart starts at the top
        // rather than wherever the shopper happened to be an hour ago.
        try {
            var saved = window.sessionStorage.getItem(key);

            if (saved !== null && window.location.hash === '') {
                window.sessionStorage.removeItem(key);

                var y = parseInt(saved, 10);

                if (!isNaN(y) && y > 0) {
                    window.scrollTo(0, y);

                    /*
                     | Applied a second time on load. The theme's preloader is still covering the
                     | page at DOMContentLoaded and its images have not been laid out yet, so the
                     | document is often shorter now than it will be — the first scrollTo can
                     | land short of where it was asked to go.
                    */
                    window.addEventListener('load', function () {
                        window.scrollTo(0, y);
                    });
                }
            }
        } catch (error) {
            // As above.
        }

        /*
         | Bubble phase, and bindScrollMemory is called LAST in init() — so every in-place
         | handler above has already run and cancelled the ones it handles. A submit that never
         | leaves the page therefore stores nothing, and cannot strand a stale position waiting
         | to hijack the next real load.
        */
        document.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;

            store();
        }, false);

        // Reachable from the in-place cart's fallback, which submits without an event. See the
        // declaration at the top of this file.
        rememberScrollPosition = store;
    }

    function init() {
        bindAutoSubmit();
        bindListFilters();
        bindBundleTotals();
        bindQuantitySteppers();
        // Before the rotation: it reads the banner's computed style to build its fade layer.
        paintHeroBackground();
        bindHeroRotation();
        bindSmoothListing();
        bindVehicleCascade();
        bindSubmitOnce();
        bindMiniCart();
        bindBasketForms();
        bindCouponCheck();
        releaseStuckPreloader();
        /*
         | LAST, and the order matters. Its submit listener is on the document in the bubble
         | phase, so registering it after bindBasketForms is what lets it see that an in-place
         | handler has already cancelled the navigation — and store nothing for a submit that is
         | not going anywhere.
        */
        bindScrollMemory();
    }

    /* ------------------------------------------------------------------ *
     | A dead man's handle on the theme's preloader.
     |
     | .preloader-area is a fixed, full-screen, white, z-index:11 sheet, and the theme removes
     | it in $(window).load(). window.load waits for EVERY subresource — every font, every
     | image, and every iframe. The contact page embeds a Google map, so one slow third-party
     | response holds the whole shop behind a white rectangle with no way for the visitor to
     | know anything is wrong.
     |
     | This does not change the normal path: if load fires first the timer is cancelled and the
     | theme's own fade runs. It only covers the case where load never comes.
     |
     | THE GUARD USED TO READ `offsetParent !== null`, AND IT COULD NEVER BE TRUE.
     |
     | offsetParent is specified to return null for a `position: fixed` element, and
     | .preloader-area is fixed (theme-style.css:1665). So the test reported "already hidden" on
     | every page, the timeout did nothing, and this dead man's handle had never once fired.
     | Measured before the fix: on a throttled cold load the sheet was plainly covering the
     | screen in a screenshot while this check said it was hidden.
     |
     | The computed `display` is read instead, because display:none is what the theme's own
     | fadeOut() finishes on. Mid-fade the sheet still computes to display:flex at a fractional
     | opacity — hiding it then is harmless, it was leaving anyway — and the load listener below
     | means a normal page never reaches this code at all.
     *------------------------------------------------------------------ */
    function releaseStuckPreloader() {
        var preloader = document.querySelector('.preloader-area');
        if (!preloader) return;

        var timer = window.setTimeout(function () {
            if (window.getComputedStyle(preloader).display !== 'none') {
                preloader.style.display = 'none';
            }
        }, 4000);

        window.addEventListener('load', function () {
            window.clearTimeout(timer);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /*
     | The price slider is bound LATER than everything else, on window.load.
     |
     | The theme creates that slider in its own DOMContentLoaded handler, and noUiSlider
     | throws if it is created twice. Binding it alongside the rest was a race between two
     | deferred scripts: when this one won, the theme's create() threw and took out the rest
     | of brator-script.js from that line onwards.
     |
     | window.load runs after every DOMContentLoaded handler, so by the time this fires the
     | theme's slider exists and there is only ever an updateOptions to do.
    */
    if (document.readyState === 'complete') {
        bindPriceSlider();
    } else {
        window.addEventListener('load', bindPriceSlider);
    }
})();
