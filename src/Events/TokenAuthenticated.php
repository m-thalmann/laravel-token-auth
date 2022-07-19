<?php

namespace TokenAuth\Events;

class TokenAuthenticated {
    /**
     * The access token that was authenticated.
     *
     * @var \TokenAuth\Contracts\AuthTokenContract|\Illuminate\Database\Eloquent\Model
     */
    public $token;

    /**
     * Create a new event instance.
     *
     * @param \TokenAuth\Contracts\AuthTokenContract|\Illuminate\Database\Eloquent\Model $token
     * @return void
     */
    public function __construct($token) {
        $this->token = $token;
    }
}
