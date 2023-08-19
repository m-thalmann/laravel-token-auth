<?php

namespace TokenAuth\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use TokenAuth\Exceptions\MissingTokenAbilitiesException;
use TokenAuth\Facades\TokenAuth;

class CheckForAnyTokenAbility {
    public function handle(
        Request $request,
        Closure $next,
        string ...$abilities
    ): mixed {
        $token = TokenAuth::currentToken();

        if ($token === null) {
            throw new AuthenticationException();
        }

        foreach ($abilities as $ability) {
            if ($token->hasAbility($ability)) {
                return $next($request);
            }
        }

        throw new MissingTokenAbilitiesException($abilities);
    }
}
