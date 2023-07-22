<?php

namespace TokenAuth\Tests;

use TokenAuth\Facades\TokenAuth;
use TokenAuth\TokenAuthServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase {
    public function setUp(): void {
        parent::setUp();
    }

    public function getEnvironmentSetUp($app) {
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

    protected function getPackageAliases($app) {
        return [
            'TokenAuth' => TokenAuth::class,
        ];
    }
}
