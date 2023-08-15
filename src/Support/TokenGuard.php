<?php

namespace TokenAuth\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Concerns\HasAuthTokens;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Support\AbstractTokenGuard;

class TokenGuard extends AbstractTokenGuard {
    protected function getTokenInstance(string $token): ?AuthTokenContract {
        return TokenAuth::getAuthTokenClass()::find(
            $this->expectedTokenType,
            $token,
            active: true
        );
    }

    protected function handleDetectedReuse(AuthTokenContract $token): void {
        TokenAuth::getAuthTokenClass()::deleteTokensFromGroup(
            $token->getGroupId()
        );
    }

    protected function maybeSetTokenOnAuthenticatable(
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
