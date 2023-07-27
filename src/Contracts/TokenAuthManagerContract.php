<?php

namespace TokenAuth\Contracts;

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
}
