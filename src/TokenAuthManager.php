<?php

namespace TokenAuth;

use Closure;
use InvalidArgumentException;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Contracts\TokenAuthManagerContract;
use TokenAuth\Models\AuthToken;

class TokenAuthManager implements TokenAuthManagerContract {
    protected string $authTokenClass = AuthToken::class;
    protected ?Closure $authTokenRetrievalCallback = null;
    protected ?Closure $authTokenAuthenticationCallback = null;
    protected bool $runsMigrations = true;

    public function getAuthTokenClass(): string {
        return $this->authTokenClass;
    }
    public function setAuthTokenClass(string $class): void {
        if (!is_subclass_of($class, AuthTokenContract::class)) {
            throw new InvalidArgumentException(
                'The AuthToken class must implement ' . AuthTokenContract::class
            );
        }

        $this->authTokenClass = $class;
    }

    public function getAuthTokenRetrievalCallback(): ?Closure {
        return $this->authTokenRetrievalCallback;
    }
    public function retrieveAuthTokensUsing(Closure $callback): void {
        $this->authTokenRetrievalCallback = $callback;
    }

    public function getAuthTokenAuthenticationCallback(): ?Closure {
        return $this->authTokenAuthenticationCallback;
    }
    public function authenticateAuthTokensUsing(Closure $callback) {
        $this->authTokenAuthenticationCallback = $callback;
    }
}
