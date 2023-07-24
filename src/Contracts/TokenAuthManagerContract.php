<?php

namespace TokenAuth\Contracts;

use Closure;

interface TokenAuthManagerContract {
    /**
     * Get the AuthToken class
     * @return string
     */
    public function getAuthTokenClass(): string;
    /**
     * Set the AuthToken class
     * @param string $class
     * @throws \InvalidArgumentException
     */
    public function setAuthTokenClass(string $class): void;

    /**
     * Get the AuthToken retrieval callback
     * @return \Closure|null
     */
    public function getAuthTokenRetrievalCallback(): ?Closure;
    /**
     * Specify a callback that should be used to fetch the auth token from the request
     * @param \Closure $callback
     */
    public function retrieveAuthTokensUsing(Closure $callback): void;

    /**
     * Get the AuthToken authentication callback
     * @return \Closure|null
     */
    public function getAuthTokenAuthenticationCallback(): ?Closure;
    /**
     * Specify a callback that should be used to authenticate tokens.
     * The callback should accept an instance of the AuthToken and return true or false depending on whether the token is valid.
     * @param \Closure $callback
     */
    public function authenticateAuthTokensUsing(Closure $callback);
}
