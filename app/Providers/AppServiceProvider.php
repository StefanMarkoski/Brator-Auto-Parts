<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Catalog\Queries\Public\GetNavigationQuery;
use App\Domain\Fitment\Queries\Internal\GetVehiclePickerQuery;
use App\Domain\Fitment\Services\VehicleSelection;
use App\Domain\Ordering\Events\ReceiptPlaced;
use App\Domain\Ordering\Listeners\Internal\SendReceiptEmail;
use App\Domain\Ordering\Models\Coupon;
use App\Domain\Ordering\Queries\Internal\GetBasketSummaryQuery;
use App\Domain\Ordering\Services\BasketResolver;
use App\Support\Database\SchemaMacros;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        SchemaMacros::register();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A parts catalogue lives or dies on its queries. Lazy loading is how a
        // listing page silently becomes 200 queries, so it is an error outside
        // production rather than a slow surprise. Note it only trips on results
        // of 2+ rows, so fixtures must always have at least two.
        Model::preventLazyLoading(! $this->app->isProduction());

        /*
         | GENERATE https:// URLS WHEN THE SITE IS SERVED OVER https://.
         |
         | Laravel decides the scheme from the connection it can see. Behind a TLS
         | terminator it sees plain HTTP, so it built every redirect, asset URL and signed
         | URL as http:// on a page the browser had loaded over https.
         |
         | That is not cosmetic. MEASURED on the deployed shop: POST /cart/add answered
         |   location: http://brator-…laravel.cloud/cart
         | which the browser blocks as mixed content. The background add therefore FAILED
         | after the server had already added the item, storefront.js fell back to a native
         | form submit, and the shopper got the product twice and was thrown onto the cart
         | — the exact behaviour the in-place cart work existed to remove.
         |
         | Keyed off APP_URL rather than the environment name, so it is explicit and local
         | (http://localhost:8090) is untouched.
         |
         | WHY THIS RATHER THAN TRUSTING THE PROXY. trustProxies is already set to the
         | private ranges, and the platform's proxy is not in one. Widening that to '*'
         | would fix the symptom by believing whatever X-Forwarded-* a visitor sends, which
         | hands them the scheme and host the whole application believes in. Forcing the
         | scheme cannot be influenced by a request header at all: it is a statement that
         | this deployment is HTTPS-only, which is true.
        */
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Models live in app/Domain/{Context}/Models, so Laravel's default guess of
        // Database\Factories\Domain\Catalog\Models\BrandFactory misses. Resolve on the
        // class basename instead — one rule for every bounded context, rather than a
        // newFactory() override on all thirty-odd models.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        Event::listen(ReceiptPlaced::class, SendReceiptEmail::class);

        // The header's mega menu needs the category tree on every page. A composer
        // keeps that out of every controller — a nav that only works on pages whose
        // controller remembered to pass it is how half a site ends up with dead links.
        View::composer(
            ['partials.header', 'partials.header-shop', 'partials.footer-top', 'partials.shop-departments',
                'home.sections.best-sellers'],
            fn ($view) => $view->with('navCategories', app(GetNavigationQuery::class)->execute())
        );

        /*
         | The promo bar's coupons. A composer rather than a query inside the Blade file, for the
         | same reason the nav is one: a partial that fetches its own data works only on pages
         | whose controller remembered to pass it, and this bar is on every storefront page.
        */
        View::composer('partials.header', fn ($view) => $view->with('advertisedCoupons', Coupon::advertised()));

        // The vehicle picker builds its own cascade state, so any page can include it.
        View::composer('partials.vehicle-picker', function ($view): void {
            $picker = app(GetVehiclePickerQuery::class);
            $selection = app(VehicleSelection::class);
            $state = $selection->picker();

            $view->with('vehiclePicker', [
                'state' => $state,
                // The chosen variant, so the Engine box can show what was picked. It lives
                // apart from the rest of the state because choosing an engine IS choosing
                // the vehicle — the other four levels are only the route to it.
                'variant' => $selection->current(),
                'years' => $picker->years(),
                'makes' => $picker->makes($state['year']),
                'models' => $state['make'] === null ? [] : $picker->models($state['make'], $state['year']),
                'names' => $state['model'] === null ? [] : $picker->variantNames($state['model'], $state['year']),
                'engines' => ($state['model'] === null || $state['name'] === null)
                    ? []
                    : $picker->engines($state['model'], $state['name'], $state['year']),
                // Whether there is anything to start again FROM. Computed here rather than in
                // the view, because the "Start again" button is now inside the picker form and
                // the in-place cascade reads this state off the response to show or hide it.
                'hasSelection' => $selection->current() !== null
                    || array_filter($state, fn ($level) => $level !== null) !== [],
            ]);
        });

        /*
         | Whether a car is chosen, for the header's "Add Vehicle" badge — which was a hardcoded
         | 0 and stayed 0 with a vehicle selected, in the header of the page whose whole point is
         | that you pick your car.
         |
         | A session read and nothing else, so it costs no query. Deliberately NOT served by
         | adding the header to the vehicle-picker composer above: that one builds the entire
         | cascade, and the picker is included INSIDE the header, so every page would have run
         | those five lookups twice.
        */
        View::composer(
            ['partials.header', 'partials.header-shop'],
            fn ($view) => $view->with('hasVehicleSelected', app(VehicleSelection::class)->current() !== null)
        );

        // The cart badge AND the mini-cart panel, on every page. The theme shipped the
        // panel with two hardcoded wheels in it, so every visitor was told they had a
        // basket they had never touched.
        View::composer(
            ['partials.header', 'partials.header-shop', 'partials.mini-cart-items'],
            function ($view): void {
                $basket = app(BasketResolver::class)->current();
                $summary = app(GetBasketSummaryQuery::class)->execute($basket);

                $view->with('basketCount', $summary->itemCount)->with('miniCart', $summary);
            }
        );
    }
}
