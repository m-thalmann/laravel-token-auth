<?php

namespace TokenAuth\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Facades\TokenAuth;

trait HasAuthTokens {
    /**
     * Return the tokens that belong to the model
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function tokens(): MorphMany {
        return $this->morphMany(
            TokenAuth::getAuthTokenClass(),
            'authenticatable'
        );
    }

    /**
     * Create an AuthTokenBuilder instance for the authenticatable and return it
     * @param \TokenAuth\Enums\TokenType $tokenType
     * @return \TokenAuth\Contracts\AuthTokenBuilderContract
     */
    public function createToken(
        TokenType $tokenType
    ): AuthTokenBuilderContract {
        return TokenAuth::getAuthTokenClass()
            ::create($tokenType)
            ->setAuthenticatable($this);
    }
}
