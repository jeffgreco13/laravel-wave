<?php

namespace Jeffgreco13\Wave;

use Illuminate\Support\ServiceProvider;
use Jeffgreco13\Wave\Commands\PullWaveCurrencies;
use Jeffgreco13\Wave\Commands\SyncWaveAll;
use Jeffgreco13\Wave\Commands\SyncWaveCustomers;

class WaveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PullWaveCurrencies::class,
                SyncWaveCustomers::class,
                SyncWaveAll::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../config/wave.php' => config_path('wave.php'),
        ], 'wave-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'wave-migrations');
    }

    public function register(): void
    {
        $this->app->singleton(WaveService::class, function () {
            return new WaveService;
        });

        $this->app->bind('laravel-wave', function ($app) {
            return $app->make(WaveService::class);
        });

        $this->mergeConfigFrom(__DIR__.'/../config/wave.php', 'wave');
    }
}
