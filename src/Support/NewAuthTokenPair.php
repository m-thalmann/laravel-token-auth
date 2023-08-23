<?php

namespace TokenAuth\Support;

class NewAuthTokenPair {
    public function __construct(
        public readonly NewAuthToken $accessToken,
        public readonly NewAuthToken $refreshToken
    ) {
    }
}
