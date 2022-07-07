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
                $auth->extend($guard, function (
                    $app,
                    $name,
                    array $config
                ) use ($auth, $tokenType) {
                    return tap(
                        $this->createGuard($auth, $tokenType, $config),
                        function ($guard) {
                            app()->refresh('request', $guard, 'setRequest');
                        }
                    );
                });
            }
        });
    }

    /**
     * Register the guard.
     *
     * @param \Illuminate\Contracts\Auth\Factory $auth
     * @param string $tokenType
     * @param array $config
     * @return RequestGuard
     */
    protected function createGuard($auth, $tokenType, $config) {
        $userProvider = null;

        if (method_exists($auth, 'createUserProvider')) {
            // since method exists we can cast to object to hide error
            $userProvider = ((object) $auth)->createUserProvider(
                $config['provider'] ?? null
            );
        }

        return new RequestGuard(
            new Guard($auth, $tokenType, $config['provider']),
            request(),
            $userProvider
        );
    }
}

