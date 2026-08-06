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
                    window.location = el.value;

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

        mount.noUiSlider.on('update', function (values) {
            minInput.value = Math.round(values[0]);
            maxInput.value = Math.round(values[1]);
            label(values);
        });

        // Apply only when the handle is released, not on every pixel of the drag.
        mount.noUiSlider.on('change', function () {
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

    function init() {
        bindAutoSubmit();
        bindListFilters();
        bindBundleTotals();
        bindQuantitySteppers();
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
