<?php

namespace NazariiKretovych\LaravelApiModelDriver;

use Illuminate\Database\Connection as ConnectionBase;
use Illuminate\Support\ServiceProvider as ServiceProviderBase;

class ServiceProvider extends ServiceProviderBase
{
    public function register()
    {
        ConnectionBase::resolverFor('api', static function ($connection, $database, $prefix, $config) {
            if (app()->has(Connection::class)) {
                return app(Connection::class);
            }

            return new Connection($connection, $database, $prefix, $config);
        });

        $this->mergeConfigFrom(__DIR__ . '/../config/api-model-driver.php', 'api-model-driver');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/api-model-driver.php' => config_path('api-model-driver.php'),
        ], 'config');
    }
}
