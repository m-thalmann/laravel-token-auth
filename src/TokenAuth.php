<?php

namespace TokenAuth;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mockery;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Contracts\HasAbilities;
use TokenAuth\Traits\HasAuthTokens;
use TokenAuth\Exceptions\MissingAbilityException;

class TokenAuth {
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
     * Create a new refresh and access token for the authenticated user with the same token group
     *
     * @param string $refreshTokenName
     * @param string $accessTokenName
     * @param array<array<string>> $tokenAbilities The first value are the abilities for the refresh token, the second one for the access token
     * @param array<int|null> $tokenExpirationMinutes The first value is the expiration time for the refresh token, the second one for the access token
     * @param bool $save Whether the tokens should be saved (default true)
     *
     * @return array<\TokenAuth\NewAuthToken> The refresh token and the access token
     */
    public static function createTokenPair(
        string $refreshTokenName,
        string $accessTokenName,
        array $tokenAbilities = [['*'], ['*']],
        array $tokenExpirationMinutes = [null, null],
        bool $save = true
    ) {
        return self::createTokenPairForUser(
            (object) auth()->user(),
            $refreshTokenName,
            $accessTokenName,
            $tokenAbilities,
            $tokenExpirationMinutes,
            $save
        );
    }

    /**
     * Create a new refresh and access token for the user with the same token group
     *
     * @param \TokenAuth\Traits\HasAuthTokens $user
     * @param string $refreshTokenName
     * @param string $accessTokenName
     * @param array<array<string>> $tokenAbilities The first value are the abilities for the refresh token, the second one for the access token
     * @param array<int|null> $tokenExpirationMinutes The first value is the expiration time for the refresh token, the second one for the access token
     * @param bool $save Whether the tokens should be saved (default true)
     *
     * @return array<\TokenAuth\NewAuthToken> The refresh token and the access token
     */
    public static function createTokenPairForUser(
        $user,
        string $refreshTokenName,
        string $accessTokenName,
        array $tokenAbilities = [['*'], ['*']],
        array $tokenExpirationMinutes = [null, null],
        bool $save = true
    ) {
        if ($user === null) {
            throw new AuthorizationException();
        }

        @[$refreshTokenAbilities, $accessTokenAbilities] = $tokenAbilities;
        @[
            $refreshTokenExpiration,
            $accessTokenExpiration,
        ] = $tokenExpirationMinutes;

        $tokenGroupId = self::getNextTokenGroupId();

        $refreshToken = $user->createToken(
            self::TYPE_REFRESH,
            $refreshTokenName,
            $tokenGroupId,
            $refreshTokenAbilities,
            $refreshTokenExpiration,
            $save
        );

        self::checkHasAllAbilities($refreshToken->token, $accessTokenAbilities);

        $accessToken = $user->createToken(
            self::TYPE_ACCESS,
            $accessTokenName,
            $tokenGroupId,
            $accessTokenAbilities,
            $accessTokenExpiration,
            $save
        );

        return [$refreshToken, $accessToken];
    }

    /**
     * Create a new refresh token (with values from the current one) and access token
     * for the current user from the refresh token he used to authenticate with.
     * The current refresh token is revoked.
     *
     * @param string $accessTokenName
     * @param array $accessTokenAbilities
     * @param array<int|null> $tokenExpirationMinutes The first value is the expiration time for the refresh token, the second one for the access token
     * @param bool $save Whether the tokens should be saved (default true)
     *
     * @return array<\TokenAuth\NewAuthToken> The refresh token and the access token
     */
    public static function rotateRefreshToken(
        string $accessTokenName,
        array $accessTokenAbilities = ['*'],
        array $tokenExpirationMinutes = [null, null],
        bool $save = true
    ) {
        /**
         * @var HasAuthTokens
         */
        $user = auth()->user();

        if ($user === null) {
            throw new AuthorizationException();
        }

        return self::rotateRefreshTokenForUser(
            $user,
            $user->currentToken(),
            $accessTokenName,
            $accessTokenAbilities,
            $tokenExpirationMinutes,
            $save
        );
    }

    /**
     * Create a new refresh token (with values from the given one) and access token for
     * the given user with the values from the given refresh token.
     * The given refresh token is revoked.
     *
     * @param \TokenAuth\Traits\HasAuthTokens $user
     * @param \TokenAuth\Contracts\AuthTokenContract $refreshToken
     * @param string $accessTokenName
     * @param array $accessTokenAbilities
     * @param array<int|null> $tokenExpirationMinutes The first value is the expiration time for the refresh token, the second one for the access token
     * @param bool $save Whether the tokens should be saved (default true)
     *
     * @return array<\TokenAuth\NewAuthToken> The refresh token and the access token
     */
    public static function rotateRefreshTokenForUser(
        $user,
        AuthTokenContract $refreshToken,
        string $accessTokenName,
        array $accessTokenAbilities = ['*'],
        array $tokenExpirationMinutes = [null, null],
        bool $save = true
    ) {
        if (
            $refreshToken === null ||
            $refreshToken->getType() !== self::TYPE_REFRESH
        ) {
            throw new AuthorizationException();
        }

        self::checkHasAllAbilities($refreshToken, $accessTokenAbilities);

        @[
            $refreshTokenExpiration,
            $accessTokenExpiration,
        ] = $tokenExpirationMinutes;

        $refreshToken->revoke()->save();

        $newRefreshToken = $user->createToken(
            self::TYPE_REFRESH,
            $refreshToken->name,
            $refreshToken->group_id,
            $refreshToken->abilities,
            $refreshTokenExpiration,
            $save
        );

        $newAccessToken = $user->createToken(
            self::TYPE_ACCESS,
            $accessTokenName,
            $refreshToken->group_id,
            $accessTokenAbilities,
            $accessTokenExpiration,
            $save
        );

        return [$newRefreshToken, $newAccessToken];
    }

    /**
     * Create a new access token for the authenticated user without a token group
     *
     * @param string $name
     * @param array $abilities
     * @param int|null $expiresInMinutes
     * @param bool $save Whether the token should be saved (default true)
     *
     * @return \TokenAuth\NewAuthToken
     */
    public static function createAccessToken(
        string $name,
        array $abilities = ['*'],
        int|null $expiresInMinutes = null,
        bool $save = true
    ) {
        /**
         * @var HasAuthTokens
         */
        $user = auth()->user();

        if ($user === null) {
            throw new AuthorizationException();
        }

        return self::createAccessTokenForUser(
            $user,
            $name,
            $abilities,
            $expiresInMinutes,
            $save
        );
    }

    /**
     * Create a new access token for the given user without a token group
     *
     * @param \TokenAuth\Traits\HasAuthTokens $user
     * @param string $name
     * @param array $abilities
     * @param int|null $expiresInMinutes
     * @param bool $save Whether the token should be saved (default true)
     *
     * @return \TokenAuth\NewAuthToken
     */
    public static function createAccessTokenForUser(
        $user,
        string $name,
        array $abilities = ['*'],
        int|null $expiresInMinutes = null,
        bool $save = true
    ) {
        return $user->createToken(
            self::TYPE_ACCESS,
            $name,
            null,
            $abilities,
            $expiresInMinutes,
            $save
        );
    }

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
        $token = Mockery::mock(self::$authTokenModel)->shouldIgnoreMissing(
            false
        );

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

    /**
     * Return the id for the next token group.
     * If no group is found in the database 1 is returned
     *
     * @return int
     */
    public static function getNextTokenGroupId() {
        $id = DB::table('auth_tokens')
            ->whereNotNull('group_id')
            ->orderByDesc('group_id')
            ->first('group_id');

        return $id !== null ? intval($id->group_id) : 1;
    }

    /**
     * Checks whether the abilities-object has all the abilities in the check-abilities array
     *
     * @param \TokenAuth\Contracts\HasAbilities $abilitiesObject
     * @param array $checkAbilities
     *
     * @throws \TokenAuth\Exceptions\MissingAbilityException If an ability is missing
     */
    private static function checkHasAllAbilities(
        HasAbilities $abilitiesObject,
        array $checkAbilities
    ) {
        if ($abilitiesObject->cant('*')) {
            foreach ($checkAbilities as $ability) {
                if (!$abilitiesObject->can($ability)) {
                    throw new MissingAbilityException(
                        $ability,
                        trans(
                            'tokenAuth::errors.refresh_token_missing_ability',
                            ['ability' => $ability]
                        )
                    );
                }
            }
        }
    }
}
