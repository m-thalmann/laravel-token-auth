<?php

namespace TokenAuth;

use InvalidArgumentException;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Contracts\TokenAuthManagerContract;

class TokenAuthManager implements TokenAuthManagerContract {
    protected string $tokenGuardClass = TokenGuard::class;

    public function getTokenGuardClass(): string {
        return $this->tokenGuardClass;
    }
    public function useTokenGuard(string $class): void {
        if (!is_subclass_of($class, AbstractTokenGuard::class)) {
            throw new InvalidArgumentException(
                'The TokenGuard class must implement ' .
                    AuthTokenContract::class
            );
        }

        $this->tokenGuardClass = $class;
    }
}
