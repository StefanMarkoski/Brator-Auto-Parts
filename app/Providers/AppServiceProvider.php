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
                'years' => $picker->years(),
                'makes' => $picker->makes($state['year']),
                'models' => $state['make'] === null ? [] : $picker->models($state['make'], $state['year']),
                'names' => $state['model'] === null ? [] : $picker->variantNames($state['model'], $state['year']),
                'engines' => ($state['model'] === null || $state['name'] === null)
                    ? []
                    : $picker->engines($state['model'], $state['name'], $state['year']),
            ]);
        });

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
