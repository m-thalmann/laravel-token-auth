<?php

namespace TokenAuth;

use Illuminate\Auth\RequestGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use TokenAuth\Console\Commands\PruneExpiredTokens;

class TokenAuthServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {
        foreach (TokenAuth::GUARDS_TOKEN_TYPES as $guard => $tokenType) {
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

        if (!app()->configurationIsCached()) {
            $this->mergeConfigFrom(
                __DIR__ . '/../config/tokenAuth.php',
                'tokenAuth'
            );
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {
        if (app()->runningInConsole()) {
            $this->registerMigrations();

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

            $this->publishes(
                [
                    __DIR__ . '/../lang' => app()->langPath('vendor/tokenAuth'),
                ],
                'token-auth-lang'
            );

            $this->commands([PruneExpiredTokens::class]);
        }

        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'tokenAuth');
        $this->configureGuard();
    }

    /**
     * Register the migration files.
     *
     * @return void
     */
    protected function registerMigrations() {
        if (TokenAuth::$runsMigrations) {
            return $this->loadMigrationsFrom(
                __DIR__ . '/../database/migrations'
            );
        }
    }

    /**
     * Configure the token-auth authentication guard.
     *
     * @return void
     */
    protected function configureGuard() {
        Auth::resolved(function ($auth) {
            foreach (TokenAuth::GUARDS_TOKEN_TYPES as $guard => $tokenType) {
                $auth->extend($guard, function () use ($auth, $tokenType) {
                    $guard = new Guard($tokenType);

                    $requestGuard = $this->createRequestGuard($auth, $guard);

                    app()->rebinding('request', function ($app, $instance) use (
                        $guard,
                        $requestGuard
                    ) {
                        $guard->reset();
                        $requestGuard->setRequest($instance);
                    });

                    return $requestGuard;
                });
            }
        });
    }

    /**
     * Create the request guard.
     *
     * @param \Illuminate\Contracts\Auth\Factory|\Illuminate\Auth\AuthManager $auth
     * @param callable $callable
     * @param array $config
     *
     * @return RequestGuard
     */
    protected function createRequestGuard($auth, $callable) {
        return new RequestGuard(
            $callable,
            request(),
            $auth->createUserProvider($config['provider'] ?? null)
        );
    }
}
