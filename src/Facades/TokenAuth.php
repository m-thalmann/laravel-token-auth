<?php

namespace TokenAuth\Facades;

use Illuminate\Support\Facades\Facade;
use TokenAuth\Contracts\TokenAuthManagerContract;

/**
 * @see \TokenAuth\Contracts\TokenAuthManagerContract
 * @see \TokenAuth\TokenAuthManager
 *
 * @method static string getAuthTokenClass()
 * @method static void useAuthToken(string $class)
 * @method static string getTokenGuardClass()
 * @method static void useTokenGuard(string $class)
 */
class TokenAuth extends Facade {
    protected static function getFacadeAccessor(): string {
        return TokenAuthManagerContract::class;
    }
}
