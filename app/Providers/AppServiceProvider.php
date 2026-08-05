<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Catalog\Queries\Public\GetNavigationQuery;
use App\Support\Database\SchemaMacros;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
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

        // The header's mega menu needs the category tree on every page. A composer
        // keeps that out of every controller — a nav that only works on pages whose
        // controller remembered to pass it is how half a site ends up with dead links.
        View::composer(
            ['partials.header', 'partials.header-shop', 'partials.footer-top'],
            fn ($view) => $view->with('navCategories', app(GetNavigationQuery::class)->execute())
        );
    }
}
