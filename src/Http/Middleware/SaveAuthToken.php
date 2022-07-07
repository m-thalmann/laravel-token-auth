<?php

namespace TokenAuth\Http\Middleware;

class SaveAuthToken {
    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Auth\AuthenticationException|\TokenAuth\Exceptions\MissingAbilityException
     */
    public function handle($request, $next) {
        $request
            ->user()
            ?->currentToken()
            ?->save();

        return $next($request);
    }
}

