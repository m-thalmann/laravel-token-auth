<?php

namespace TokenAuth\Traits;

use Carbon\Carbon;
use Illuminate\Support\Str;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\NewAuthToken;
use TokenAuth\TokenAuth;

trait HasAuthTokens {
    /**
     * @var \TokenAuth\Contracts\AuthTokenContract The token the user is using for the current request (refresh / access).
     */
    protected $token;

    /**
     * Return the tokens that belong to the model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function tokens() {
        return $this->morphMany(TokenAuth::$authTokenModel, 'tokenable');
    }

    /**
     * Determine if the current token has the given ability.
     *
     * @param string $ability
     * @return bool
     */
    public function tokenCan(string $ability) {
        return $this->token && $this->token->can($ability);
    }

    /**
     * Create a new token for the user with the given type.
     *
     * @param string $type
     * @param string $name
     * @param int|null $tokenGroupId
     * @param array $abilities
     * @param int|null $expiresInMinutes If the value is -1 the token doesn't expire
     *
     * @return \TokenAuth\NewAuthToken
     */
    public function createToken(
        string $type,
        string $name,
        int|null $tokenGroupId = null,
        array $abilities = ['*'],
        int|null $expiresInMinutes = null
    ) {
        if ($expiresInMinutes === -1) {
            $expiresInMinutes = null;
        } elseif ($expiresInMinutes === null) {
            $expiresInMinutes = config(
                "tokenAuth.{$type}_token_expiration",
                60
            );
        }

        $plainTextToken = Str::random(64);

        /**
         * @var \TokenAuth\Contracts\AuthTokenContract
         */
        $token = $this->tokens()->create([
            'type' => $type,
            'group_id' => $tokenGroupId,
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' =>
                $expiresInMinutes !== null
                    ? Carbon::now()->addMinutes($expiresInMinutes)
                    : null,
        ]);

        return new NewAuthToken($token, $plainTextToken);
    }

    /**
     * Get the token currently associated with the user.
     *
     * @return \TokenAuth\Contracts\AuthTokenContract
     */
    public function currentToken() {
        return $this->token;
    }

    /**
     * Get the tokens with the same group as the current one
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function tokensInCurrentGroup() {
        if ($this->token === null || $this->token->group_id === null) {
            return null;
        }

        return $this->tokens()->where('group_id', $this->token->group_id);
    }

    /**
     * Set the current token for the user.
     *
     * @param \TokenAuth\Contracts\AuthTokenContract $token
     * @return $this
     */
    public function withToken(AuthTokenContract $token) {
        $this->token = $token;

        return $this;
    }
}

