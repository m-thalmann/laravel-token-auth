<?php

namespace TokenAuth;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Mockery;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Traits\CanCreateTokens;

class TokenAuth {
    use CanCreateTokens;

    public const TYPE_REFRESH = 'refresh';
    public const TYPE_ACCESS = 'access';

    public const GUARDS_TOKEN_TYPES = [
        'token' => self::TYPE_ACCESS,
        'token-refresh' => self::TYPE_REFRESH,
    ];

    /**
     * @var string The token client model class name.
     */
    public static $authTokenModel = \TokenAuth\Models\AuthToken::class;

    /**
     * @var callable|null A callback that can get the token from the request.
     */
    public static $authTokenRetrievalCallback;

    /**
     * @var callable|null A callback that can add to the validation of the auth token.
     */
    public static $authTokenAuthenticationCallback;

    /**
     * @var bool Indicates if the migrations will be run.
     */
    public static $runsMigrations = true;

    /**
     * @var bool Indicates if the token should be saved after setting `last_used_at` on authentication.
     *
     * If this is false the token has to be manually saved on each request or you can use the `SaveAuthToken` middleware
     */
    public static $saveTokenOnAuthentication = true;

    /**
     * Set the current user for the application with the given abilities.
     * Returns the mocked token that was used
     *
     * @param \Illuminate\Contracts\Auth\Authenticatable|\TokenAuth\Traits\HasAuthTokens $user
     * @param array $abilities
     * @param string $guard
     * @return \TokenAuth\Contracts\AuthTokenContract
     */
    public static function actingAs($user, $abilities = [], $guard = 'token') {
        if ($user === null) {
            app('auth')->forgetGuards();

            return null;
        }

        /**
         * @var \Mockery\MockInterface|\Mockery\LegacyMockInterface|AuthTokenContract
         */
        $token = Mockery::mock(self::$authTokenModel);
        $token->shouldIgnoreMissing(false);

        if (in_array('*', $abilities)) {
            $token
                ->shouldReceive('can')
                ->withAnyArgs()
                ->andReturn(true);
            $token
                ->shouldReceive('cant')
                ->withAnyArgs()
                ->andReturn(false);
        } else {
            foreach ($abilities as $ability) {
                $token
                    ->shouldReceive('can')
                    ->with($ability)
                    ->andReturn(true);
                $token
                    ->shouldReceive('cant')
                    ->with($ability)
                    ->andReturn(false);
            }
        }

        $token
            ->shouldReceive('getType')
            ->andReturn(self::GUARDS_TOKEN_TYPES[$guard]);

        $user->withToken($token);

        if (isset($user->wasRecentlyCreated) && $user->wasRecentlyCreated) {
            $user->wasRecentlyCreated = false;
        }

        app('auth')
            ->guard($guard)
            ->setUser($user);

        app('auth')->shouldUse($guard);

        return $token;
    }

    /**
     * Set the auth token model name.
     *
     * @param string $model
     * @return void
     */
    public static function useAuthTokenModel($model) {
        if (!is_subclass_of($model, AuthTokenContract::class)) {
            throw new InvalidArgumentException(
                'The AuthToken model must implement ' . AuthTokenContract::class
            );
        }
        if (!is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException(
                'The AuthToken model must be an Eloquent model'
            );
        }

        static::$authTokenModel = $model;
    }

    /**
     * Specify a callback that should be used to fetch the auth token from the request.
     *
     * @param callable $callback
     * @return void
     */
    public static function getAuthTokenFromRequestUsing(callable $callback) {
        static::$authTokenRetrievalCallback = $callback;
    }

    /**
     * Specify a callback that should be used to authenticate tokens.
     *
     * @param callable $callback
     * @return void
     */
    public static function authenticateAuthTokensUsing(callable $callback) {
        static::$authTokenAuthenticationCallback = $callback;
    }

    /**
     * Configure token-auth to not register its migrations.
     */
    public static function ignoreMigrations() {
        static::$runsMigrations = false;
    }

    /**
     * Configure the guard to not save the token after setting `last_used` on authentication
     */
    public static function dontSaveTokenOnAuthentication() {
        static::$saveTokenOnAuthentication = false;
    }
}
