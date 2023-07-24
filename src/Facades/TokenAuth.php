<?php

namespace TokenAuth\Facades;

use Illuminate\Support\Facades\Facade;
use TokenAuth\Contracts\TokenAuthManagerContract;

/**
 * @see \TokenAuth\Contracts\TokenAuthManagerContract
 * @see \TokenAuth\TokenAuthManager
 *
 * @method static string getAuthTokenClass()
 * @method static void setAuthTokenClass(string $class)
 * @method static \Closure|null getAuthTokenRetrievalCallback()
 * @method static void retrieveAuthTokensUsing(\Closure $callback)
 * @method static \Closure|null getAuthTokenAuthenticationCallback()
 * @method static void authenticateAuthTokensUsing(\Closure $callback)
 */
class TokenAuth extends Facade {
    protected static function getFacadeAccessor(): string {
        return TokenAuthManagerContract::class;
    }
}
