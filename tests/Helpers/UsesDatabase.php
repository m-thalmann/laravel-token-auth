<?php

namespace TokenAuth\Tests\Helpers;

trait UsesDatabase {
    public function getEnvironmentSetUp($app): void {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void {
        $this->loadLaravelMigrations();
    }
}
