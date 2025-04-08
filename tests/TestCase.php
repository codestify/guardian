<?php

namespace Shah\Guardian\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Shah\Guardian\GuardianServiceProvider;
use Shah\Guardian\Prevention\ContentProtector;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Shah\\Guardian\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Register test implementations if not already registered
        if (! $this->app->bound(ContentProtector::class)) {
            $this->app->singleton(ContentProtector::class, function () {
                return new ContentProtector;
            });
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            GuardianServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Setup default database to use sqlite :memory:
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set guardian config
        config()->set('guardian.enabled', true);
        config()->set('guardian.detection.server_enabled', true);
        config()->set('guardian.detection.client_enabled', true);
        config()->set('guardian.detection.threshold', 60);
        config()->set('guardian.prevention.strategy', 'block');
        config()->set('guardian.prevention.protect_content', true);
        config()->set('guardian.protect_all_content', true);

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }
}
