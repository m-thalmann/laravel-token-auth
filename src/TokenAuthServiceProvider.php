<?php

namespace TokenAuth;

use Illuminate\Support\ServiceProvider;
use TokenAuth\Facades\TokenAuth;

class TokenAuthServiceProvider extends ServiceProvider {
    public function register() {
        if (!app()->configurationIsCached()) {
            $this->mergeConfigFrom(
                __DIR__ . '/../config/tokenAuth.php',
                'tokenAuth'
            );
        }

        $this->app->singleton('tokenAuth', function ($app) {
            return new TokenAuth();
        });
    }

    public function boot() {
        $this->registerMigrations();

        if (app()->runningInConsole()) {
            $this->publishes(
                [
                    __DIR__ . '/../database/migrations' => database_path(
                        'migrations'
                    ),
                ],
                'token-auth-migrations'
            );

            $this->publishes(
                [
                    __DIR__ . '/../config/tokenAuth.php' => config_path(
                        'tokenAuth.php'
                    ),
                ],
                'token-auth-config'
            );
        }
    }

    protected function registerMigrations() {
        if (TokenAuth::getRunsMigrations()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }
}
