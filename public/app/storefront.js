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

            if (isNaN(fade)) fade = 900;

            /*
             | Somebody who has asked their system for less animation gets the picture
             | swapped outright. A cross-fade is decoration; the pictures are the content.
            */
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                fade = 0;
            }

            images.forEach(function (src) {
                var pre = new Image();
                pre.src = src;
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

            function show(index) {
                var previous = current;
                current = ((index % images.length) + images.length) % images.length;

                dots.forEach(function (dot, i) {
                    dot.classList.toggle('is-active', i === current);
                });

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

            function start() {
                stop();
                timer = window.setInterval(function () {
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

    function init() {
        bindAutoSubmit();
        bindListFilters();
        bindBundleTotals();
        bindQuantitySteppers();
        bindHeroRotation();
        bindSmoothListing();
        bindVehicleCascade();
        releaseStuckPreloader();
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
     *------------------------------------------------------------------ */
    function releaseStuckPreloader() {
        var preloader = document.querySelector('.preloader-area');
        if (!preloader) return;

        var timer = window.setTimeout(function () {
            // offsetParent is null once the theme has faded it out, so a normal page that
            // simply took a while is left alone.
            if (preloader.offsetParent !== null) {
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
