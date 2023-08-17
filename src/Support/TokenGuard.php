<?php

namespace TokenAuth\Support;

use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Support\AbstractTokenGuard;

class TokenGuard extends AbstractTokenGuard {
    protected function getTokenInstance(string $token): ?AuthTokenContract {
        return TokenAuth::getAuthTokenClass()::find(
            $this->expectedTokenType,
            $token,
            mustBeActive: true
        );
    }

    protected function handleDetectedReuse(AuthTokenContract $token): void {
        TokenAuth::getAuthTokenClass()::deleteTokensFromGroup(
            $token->getGroupId()
        );
    }
}
