<?php

namespace Jeffgreco13\Wave\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Jeffgreco13\Wave\WaveServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadWaveMigrations();
    }

    protected function getPackageProviders($app): array
    {
        return [
            WaveServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('wave.access_token', 'test-token');
        $app['config']->set('wave.graphql_uri', 'https://gql.waveapps.com/graphql/public');
        $app['config']->set('wave.business_id', 'test-business-id');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('queue.default', 'sync');
    }

    /**
     * The migrations ship as .stub files (so they can be published). Load and
     * run each one against the in-memory test database.
     */
    protected function loadWaveMigrations(): void
    {
        $stubs = glob(__DIR__.'/../database/migrations/*.php.stub');

        foreach ($stubs as $stub) {
            $migration = require $stub;
            $migration->up();
        }
    }
}
