<?php

namespace TokenAuth\Tests\Helpers;

use Illuminate\Database\Schema\Blueprint;

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
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    protected function createUsersTable(): void {
        $schema = $this->app->db->connection()->getSchemaBuilder();

        $schema->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email');
            $table->string('password');
            $table->timestamps();
        });
    }
}
