<?php

namespace TokenAuth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use TokenAuth\Contracts\TokenAuthManagerContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Facades\TokenAuth;

class TokenAuthServiceProvider extends ServiceProvider {
    public function register(): void {
        if (!app()->configurationIsCached()) {
            $this->mergeConfigFrom(
                __DIR__ . '/../config/tokenAuth.php',
                'tokenAuth'
            );
        }

        $this->app->singleton(TokenAuthManagerContract::class, function () {
            return new TokenAuthManager();
        });

        $this->registerGuards();
    }

    public function boot(): void {
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

        $this->configureGuards();
    }

    protected function registerMigrations(): void {
        if (config('tokenAuth.run_migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    protected function registerGuards(): void {
        foreach (TokenType::cases() as $tokenType) {
            $guard = $tokenType->getGuardName();

            config([
                "auth.guards.$guard" => array_merge(
                    [
                        'driver' => $guard,
                        'provider' => null,
                    ],
                    config("auth.guards.$guard", [])
                ),
            ]);
        }
    }

    protected function configureGuards(): void {
        $guardClass = TokenAuth::getTokenGuardClass();

        foreach (TokenType::cases() as $tokenType) {
            $guard = new $guardClass($tokenType);

            Auth::viaRequest($tokenType->getGuardName(), $guard);
        }
    }
}
