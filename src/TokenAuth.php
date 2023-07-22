<?php

namespace TokenAuth;

use Closure;
use InvalidArgumentException;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Models\AuthToken;

class TokenAuth {
    protected string $authTokenClass = AuthToken::class;
    protected ?Closure $authTokenRetrievalCallback = null;
    protected ?Closure $authTokenAuthenticationCallback = null;
    protected bool $runsMigrations = true;

    /**
     * Get the AuthToken class
     * @return string
     */
    public function getAuthTokenClass(): string {
        return $this->authTokenClass;
    }
    /**
     * Set the AuthToken class
     * @param string $class
     * @throws InvalidArgumentException
     */
    public function setAuthTokenClass(string $class): void {
        if (!is_subclass_of($class, AuthTokenContract::class)) {
            throw new InvalidArgumentException(
                'The AuthToken class must implement ' . AuthTokenContract::class
            );
        }

        $this->authTokenClass = $class;
    }

    /**
     * Get the AuthToken retrieval callback
     * @return Closure|null
     */
    public function getAuthTokenRetrievalCallback(): ?Closure {
        return $this->authTokenRetrievalCallback;
    }
    /**
     * Specify a callback that should be used to fetch the auth token from the request
     * @param Closure $callback
     */
    public function retrieveAuthTokensUsing(Closure $callback): void {
        $this->authTokenRetrievalCallback = $callback;
    }

    /**
     * Get the AuthToken authentication callback
     * @return Closure|null
     */
    public function getAuthTokenAuthenticationCallback(): ?Closure {
        return $this->authTokenAuthenticationCallback;
    }
    /**
     * Specify a callback that should be used to authenticate tokens.
     * The callback should accept an instance of the AuthToken and return true or false depending on whether the token is valid.
     * @param Closure $callback
     */
    public function authenticateAuthTokensUsing(Closure $callback) {
        $this->authTokenAuthenticationCallback = $callback;
    }

    /**
     * Check if should register migrations
     * @return bool
     */
    public function getRunsMigrations(): bool {
        return $this->runsMigrations;
    }
    /**
     * Configure to not register migrations
     */
    public function ignoreMigrations(): void {
        $this->runsMigrations = false;
    }
}
