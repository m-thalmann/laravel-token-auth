<?php

namespace TokenAuth\Tests\Helpers;

use TokenAuth\TokenAuthServiceProvider;

trait SetsUpDatabase {
    protected function getEnvironmentSetUp($app) {
        $app['config']->set('database.default', 'testbench');

        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations() {
        $this->loadLaravelMigrations();
    }

    protected function getPackageProviders($app) {
        return [TokenAuthServiceProvider::class];
    }
}
