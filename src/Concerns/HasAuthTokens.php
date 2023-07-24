<?php

namespace TokenAuth\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Models\AuthToken;

trait HasAuthTokens {
    /**
     * @var \TokenAuth\Contracts\AuthTokenContract|null The token the user is using for the current request
     */
    protected ?AuthTokenContract $authToken = null;

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
     * Determine if the current token has the given ability
     * @param string $ability
     * @return bool
     */
    public function tokenHasAbility(string $ability): bool {
        return $this->authToken && $this->authToken->hasAbility($ability);
    }

    /**
     * Get the token currently associated with the user
     * @return \TokenAuth\Contracts\AuthTokenContract|null
     */
    public function currentToken(): ?AuthTokenContract {
        return $this->authToken;
    }

    /**
     * Set the current token for the user
     * @param \TokenAuth\Contracts\AuthTokenContract $authToken
     * @return $this
     */
    public function withToken(AuthTokenContract $authToken): static {
        $this->authToken = $authToken;
        return $this;
    }

    /**
     * Remove the current token from the user
     * @return $this
     */
    public function clearToken(): static {
        $this->authToken = null;
        return $this;
    }

    /**
     * Create an AuthTokenBuilder instance for the authenticable and return it
     * @param \TokenAuth\Enums\TokenType $tokenType
     * @return \TokenAuth\Contracts\AuthTokenBuilderContract
     */
    public function createToken(
        TokenType $tokenType
    ): AuthTokenBuilderContract {
        return AuthToken::create($tokenType)->setAuthenticable($this);
    }
}
