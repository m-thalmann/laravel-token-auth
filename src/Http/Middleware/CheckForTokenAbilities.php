<?php

namespace TokenAuth\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use TokenAuth\Exceptions\MissingTokenAbilitiesException;
use TokenAuth\Facades\TokenAuth;

class CheckForTokenAbilities {
    public function handle(
        Request $request,
        Closure $next,
        string ...$abilities
    ): mixed {
        $token = TokenAuth::currentToken();

        if ($token === null) {
            throw new AuthenticationException();
        }

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
