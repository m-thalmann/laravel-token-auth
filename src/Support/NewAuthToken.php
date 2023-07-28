<?php

namespace TokenAuth\Support;

use TokenAuth\Contracts\AuthTokenContract;

class NewAuthToken {
    public function __construct(
        public readonly AuthTokenContract $token,
        public readonly string $plainTextToken
    ) {
    }
}
