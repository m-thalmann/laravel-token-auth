<?php

namespace TokenAuth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use TokenAuth\Support\TokenPairBuilder;

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
    public function useAuthToken(string $class): void;

    /**
     * Get the class used as TokenGuard
     * @return string
     */
    public function getTokenGuardClass(): string;
    /**
     * Set the class used as TokenGuard
     * @param string $class
     * @throws \InvalidArgumentException
     */
    public function useTokenGuard(string $class): void;

    /**
     * Create a new token pair builder for the given authenticable
     * and generates the group id for it (if set)
     * @param \Illuminate\Contracts\Auth\Authenticatable $authenticatable
     * @param bool $generateGroupId
     * @return TokenPairBuilder
     */
    public function createTokenPair(
        Authenticatable $authenticatable,
        bool $generateGroupId = true
    ): TokenPairBuilder;

    /**
     * Creates a new token pair builder with the properties from the given refresh token.
     * When the pair is built, the refresh token is revoked and the associated access tokens are deleted (if set).
     * @param \TokenAuth\Contracts\AuthTokenContract $refreshToken
     * @param bool $deleteAccessToken
     * @return \TokenAuth\Support\NewAuthTokenPair
     */
    public function rotateTokenPair(
        AuthTokenContract $refreshToken,
        bool $deleteAccessToken = true
    ): TokenPairBuilder;
}
