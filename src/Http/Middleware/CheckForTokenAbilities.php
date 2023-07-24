<?php

namespace TokenAuth\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use TokenAuth\Exceptions\MissingTokenAbilitiesException;

class CheckForTokenAbilities {
    public function handle(
        Request $request,
        Closure $next,
        string ...$abilities
    ): Response {
        if (!$request->user() || !$request->user()->currentToken()) {
            throw new AuthenticationException();
        }

        /**
         * @var \TokenAuth\Contracts\AuthTokenContract $token
         */
        $token = $request->user()->currentToken();

        $missingAbilities = [];

        foreach ($abilities as $ability) {
            if (!$token->hasAbility($ability)) {
                $missingAbilities[] = $ability;
            }
        }

        if (count($missingAbilities) > 0) {
            throw new MissingTokenAbilitiesException($missingAbilities);
        }

        return $next($request);
    }
}
