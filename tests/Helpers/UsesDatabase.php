<?php

namespace TokenAuth\Tests\Helpers;

use Illuminate\Database\Schema\Blueprint;
use TokenAuth\Tests\Helpers\Models\UserTestModel;

trait UsesDatabase {
    public function getEnvironmentSetUp(mixed $app): void {
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

    protected function createTestUser() {
        return UserTestModel::forceCreate([
            'email' => 'test@example.com',
            'password' =>
                '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ]);
    }
}
