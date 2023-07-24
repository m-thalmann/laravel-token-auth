<?php

namespace TokenAuth\Events;

use TokenAuth\Contracts\AuthTokenContract;

class TokenAuthenticated {
    public function __construct(public readonly AuthTokenContract $token) {
    }
}
