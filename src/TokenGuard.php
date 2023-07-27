<?php

namespace TokenAuth;

use Illuminate\Contracts\Auth\Authenticatable;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Concerns\HasAuthTokens;
use TokenAuth\Models\AuthToken;
use TokenAuth\Support\AbstractTokenGuard;

class TokenGuard extends AbstractTokenGuard {
    protected function getTokenInstance(string $token): ?AuthTokenContract {
        return $this->authTokenClass::find(
            $this->expectedTokenType,
            $token,
            active: true
        );
    }

    protected function handleDetectedReuse(AuthTokenContract $token): void {
        $this->authTokenClass::deleteTokensFromGroup($token->group_id);
    }

    protected function maybeSetTokenOnAuthenticable(
        Authenticatable $authenticatable,
        AuthTokenContract $token
    ): void {
        if (
            !in_array(
                HasAuthTokens::class,
                class_uses_recursive(get_class($authenticatable))
            )
        ) {
            return;
        }

        $authenticatable->withToken($token);
    }
}
