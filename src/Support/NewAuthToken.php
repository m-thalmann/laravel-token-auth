<?php

namespace TokenAuth\Support;

use TokenAuth\Contracts\AuthTokenContract;

class NewAuthToken {
    /**
     * Create a new access token result.
     *
     * @param \TokenAuth\Contracts\AuthTokenContract $token
     * @param string $plainTextToken
     */
    public function __construct(
        public readonly AuthTokenContract $token,
        public readonly string $plainTextToken
    ) {
    }
}
