<?php

namespace TokenAuth\Facades;

use Illuminate\Support\Facades\Facade;
use TokenAuth\Contracts\TokenAuthManagerContract;

/**
 * @see \TokenAuth\Contracts\TokenAuthManagerContract
 * @see \TokenAuth\TokenAuthManager
 *
 * @method static string getAuthTokenClass()            Get the AuthToken class
 * @method static void useAuthToken(string $class)      Set the AuthToken class
 * @method static string getTokenGuardClass()           Get the class used as TokenGuard
 * @method static void useTokenGuard(string $class)     Set the class used as TokenGuard
 * @method static \TokenAuth\Support\AuthTokenPairBuilder createTokenPair(Authenticatable $authenticatable, bool $generateGroupId = true)    Create a new token pair builder for the given authenticatable and generates the group id for it (if set)
 * @method static \TokenAuth\Support\AuthTokenPairBuilder rotateTokenPair(\TokenAuth\Contracts\AuthTokenContract $refreshToken, bool $deleteAccessToken = true)         Creates a new token pair builder with the properties from the given refresh token. When the pair is built, the refresh token is revoked and the associated access tokens are deleted (if set).
 * @method static \TokenAuth\Contracts\AuthTokenContract|null actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, array $abilities = [], \TokenAuth\Enums\TokenType $tokenType = null)    Set the current user for the application with the given abilities. Returns the mocked token that was used
 */
class TokenAuth extends Facade {
    protected static function getFacadeAccessor(): string {
        return TokenAuthManagerContract::class;
    }
}
