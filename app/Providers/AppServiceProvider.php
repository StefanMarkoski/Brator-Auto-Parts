<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Database\SchemaMacros;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
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
    }
}
