<?php

namespace TokenAuth\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use TokenAuth\Exceptions\MissingAbilityException;

class CheckForAnyAbility {
    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param mixed ...$abilities
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Auth\AuthenticationException|\TokenAuth\Exceptions\MissingAbilityException
     */
    public function handle($request, $next, ...$abilities) {
        if (!$request->user() || !$request->user()->currentToken()) {
            throw new AuthenticationException();
        }

        foreach ($abilities as $ability) {
            if ($request->user()->tokenCan($ability)) {
                return $next($request);
            }
        }

        throw new MissingAbilityException($abilities);
    }
}

