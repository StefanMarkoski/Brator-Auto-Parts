/*
 | Storefront progressive enhancement.
 |
 | WHY THIS EXISTS, and why it is not Alpine.
 |
 | The interactive bits were first written with Alpine directives (x-on, x-model,
 | x-show, x-text) — and Alpine is only in the ADMIN bundle. The storefront loads the
 | purchased theme's jQuery and nothing else, so every one of those directives was inert:
 | the vehicle cascade never advanced, filter checkboxes never applied, the sort select
 | did nothing, the brand search box did nothing, and the bundle total never moved.
 |
 | Rather than ship a second framework onto pages already running the theme's jQuery —
 | which is exactly the collision I argued against when isolating the admin panel — this
 | is a small vanilla file with no dependencies, served straight off disk like the theme's
 | own assets. No bundler touches the storefront, which is the promise that keeps it
 | byte-identical to what was bought.
 |
 | Everything here is ENHANCEMENT. Every form still works with JavaScript disabled,
 | because every one of them has a real submit button.
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

            el.addEventListener('change', function () {
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
            });
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
                    var hit = needle === '' ||
                        row.getAttribute('data-filter-label').toLowerCase().indexOf(needle) !== -1;
                    row.style.display = hit ? '' : 'none';
                });
            });
        });
    }

    /* ------------------------------------------------------------------ *
     | "Frequently Bought Together": recompute the combined total as items
     | are ticked. Rendered correct server-side first, so this only reacts.
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
                    if (box.checked) minor += parseInt(box.getAttribute('data-bundle-price'), 10) || 0;
                });

                // Formatted the same way the server does: dot thousands, comma decimals.
                var major = (minor / 100).toFixed(2).split('.');
                var whole = major[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                output.textContent = whole + ',' + major[1] + ' ' + symbol;
            }

            boxes.forEach(function (box) {
                box.addEventListener('change', recompute);
            });

            recompute();
        });
    }

    function init() {
        bindAutoSubmit();
        bindListFilters();
        bindBundleTotals();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
