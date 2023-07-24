<?php

namespace TokenAuth\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use TokenAuth\Exceptions\MissingTokenAbilitiesException;

class CheckForAnyTokenAbility {
    public function handle(
        Request $request,
        Closure $next,
        string ...$abilities
    ) {
        if (!$request->user() || !$request->user()->currentToken()) {
            throw new AuthenticationException();
        }

        /**
         * @var \TokenAuth\Contracts\AuthTokenContract $token
         */
        $token = $request->user()->currentToken();

        foreach ($abilities as $ability) {
            if ($token->hasAbility($ability)) {
                return $next($request);
            }
        }

        throw new MissingTokenAbilitiesException($abilities);
    }
}
