<?php

namespace Uadevteampackages\Imitator;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Uadevteampackages\Imitator\Graph\GraphClient;
use Uadevteampackages\Imitator\Http\Middleware\EnsureLocalEnvironment;
use Uadevteampackages\Imitator\Http\Middleware\InjectImitatorBanner;

class ImitatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/imitator.php', 'imitator');

        $this->app->singleton(Imitator::class);
        $this->app->singleton(GraphClient::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/imitator.php' => config_path('imitator.php'),
            ], 'imitator-config');

            $this->publishes([
                __DIR__.'/resources/views' => resource_path('views/vendor/imitator'),
            ], 'imitator-views');
        }

        $this->loadViewsFrom(__DIR__.'/resources/views', 'imitator');

        if (! $this->app->make(Imitator::class)->enabled()) {
            return;
        }

        $this->registerMiddleware();
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
    }

    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('imitator.local', EnsureLocalEnvironment::class);

        if (! config('imitator.inject_banner', true)) {
            return;
        }

        /** @var Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(InjectImitatorBanner::class);
    }
}
