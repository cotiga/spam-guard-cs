<?php

namespace Cotiga\SpamGuard;

use Cotiga\SpamGuard\Services\FormSpamGuard;
use Cotiga\SpamGuard\Services\HttpErrorGuard;
use Illuminate\Support\ServiceProvider;

class SpamGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/spam-guard.php', 'spam-guard');

        $this->app->singleton(FormSpamGuard::class);
        $this->app->singleton(HttpErrorGuard::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'spam-guard');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/spam-guard.php' => config_path('spam-guard.php'),
            ], 'spam-guard-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'spam-guard-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/spam-guard'),
            ], 'spam-guard-views');
        }
    }
}
