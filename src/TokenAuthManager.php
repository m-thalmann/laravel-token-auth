<?php

namespace TokenAuth;

use InvalidArgumentException;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Contracts\TokenAuthManagerContract;
use TokenAuth\Models\AuthToken;
use TokenAuth\Support\AbstractTokenGuard;

class TokenAuthManager implements TokenAuthManagerContract {
    protected string $authTokenClass = AuthToken::class;
    protected string $tokenGuardClass = TokenGuard::class;

    public function getAuthTokenClass(): string {
        return $this->authTokenClass;
    }
    public function useAuthToken(string $class): void {
        if (!is_subclass_of($class, AuthTokenContract::class)) {
            throw new InvalidArgumentException(
                'The AuthToken class must implement ' . AuthTokenContract::class
            );
        }

        $this->authTokenClass = $class;
    }

    public function getTokenGuardClass(): string {
        return $this->tokenGuardClass;
    }
    public function useTokenGuard(string $class): void {
        if (!is_subclass_of($class, AbstractTokenGuard::class)) {
            throw new InvalidArgumentException(
                'The TokenGuard class must extend from ' .
                    AbstractTokenGuard::class
            );
        }

        $this->tokenGuardClass = $class;
    }
}
