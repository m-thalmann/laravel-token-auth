<?php

namespace TokenAuth\Events;

use TokenAuth\Contracts\AuthTokenContract;

class RevokedTokenReused {
    public function __construct(public readonly AuthTokenContract $token) {
    }
}
