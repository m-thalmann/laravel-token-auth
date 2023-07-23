<?php

namespace TokenAuth\Support;

use TokenAuth\Contracts\AuthTokenContract;

class NewAuthToken {
    public readonly AuthTokenContract $token;
    public readonly string $plainTextToken;

    /**
     * Create a new access token result.
     *
     * @param \TokenAuth\Contracts\AuthTokenContract $token
     * @param string $plainTextToken
     */
    public function __construct(
        AuthTokenContract $token,
        string $plainTextToken
    ) {
        $this->token = $token;
        $this->plainTextToken = $plainTextToken;
    }
}
