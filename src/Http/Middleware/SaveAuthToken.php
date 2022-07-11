<?php

namespace TokenAuth\Http\Middleware;

use InvalidArgumentException;

class SaveAuthToken {
    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param string $when When the token should be saved (before / after)
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Auth\AuthenticationException|\TokenAuth\Exceptions\MissingAbilityException
     */
    public function handle($request, $next, string $when = 'after') {
        if (!in_array($when, ['before', 'after'])) {
            throw new InvalidArgumentException(
                'The when parameter must be either before or after'
            );
        }

        if ($when === 'before') {
            echo 'save before';
            $this->saveToken($request);
        }

        $response = $next($request);

        if ($when === 'after') {
            echo 'save after';
            $this->saveToken($request);
        }

        return $response;
    }

    private function saveToken($request) {
        $request
            ->user()
            ?->currentToken()
            ?->save();
    }
}
