<?php

namespace TokenAuth\Events;

class RevokedTokenReused {
    /**
     * The reused token.
     *
     * @var \TokenAuth\Contracts\AuthTokenContract
     */
    public $token;

    /**
     * Create a new event instance.
     *
     * @param \TokenAuth\Contracts\AuthTokenContract $token
     * @return void
     */
    public function __construct($token) {
        $this->token = $token;
    }
}

