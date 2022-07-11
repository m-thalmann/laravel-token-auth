<?php

namespace TokenAuth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use TokenAuth\Events\RevokedTokenReused;
use TokenAuth\Events\TokenAuthenticated;
use TokenAuth\Traits\HasAuthTokens;

class Guard {
    /**
     * @var \Illuminate\Contracts\Auth\Factory The authentication factory implementation.
     */
    protected $auth;

    /**
     * @var string The expected type of the token (refresh / access)
     */
    protected $tokenType;

    /**
     * Create a new guard instance.
     *
     * @param \Illuminate\Contracts\Auth\Factory $auth
     * @param string $tokenType
     * @return void
     */
    public function __construct(AuthFactory $auth, string $tokenType) {
        $this->auth = $auth;
        $this->tokenType = $tokenType;
    }

    /**
     * Return the expected token type
     *
     * @return string
     */
    public function getTokenType() {
        return $this->tokenType;
    }

    /**
     * Retrieve the authenticated user for the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @return mixed
     */
    public function __invoke(Request $request) {
        if ($token = $this->getTokenFromRequest($request)) {
            /**
             * @var \Illuminate\Database\Eloquent\Model
             */
            $authToken = $this->getTokenInstance($token);

            if (
                !$this->isValidToken($authToken) ||
                !$this->supportsTokens($authToken->tokenable)
            ) {
                return;
            }

            $tokenable = $authToken->tokenable->withToken($authToken);

            event(new TokenAuthenticated($authToken));

            $authToken->forceFill(['last_used_at' => now()]);

            if (TokenAuth::$saveTokenOnAuthentication) {
                $authToken->save();
            }

            return $tokenable;
        }
    }

    /**
     * Get the token from the request.
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    protected function getTokenFromRequest(Request $request) {
        if (is_callable(TokenAuth::$authTokenRetrievalCallback)) {
            return (string) (TokenAuth::$authTokenRetrievalCallback)(
                $request,
                $this->tokenType
            );
        }

        return $request->bearerToken();
    }

    /**
     * Find the appropriate model instance of the given token (access / refresh)
     *
     * @param string $token
     *
     * @throws InvalidArgumentException If the expected token type is not supported
     *
     * @return \TokenAuth\Contracts\AuthTokenContract
     */
    protected function getTokenInstance($token) {
        $model = TokenAuth::$authTokenModel;

        if ($this->tokenType === TokenAuth::TYPE_ACCESS) {
            return $model::findAccessToken($token);
        } elseif ($this->tokenType === TokenAuth::TYPE_REFRESH) {
            return $model::findRefreshToken($token);
        } else {
            throw new InvalidArgumentException(
                "The token-type '{$this->tokenType}' does not exist"
            );
        }
    }

    /**
     * Determine if the provided token is valid.
     *
     * @param mixed $token
     * @return bool
     */
    protected function isValidToken($token): bool {
        if (!$token) {
            return false;
        }

        // reuse-detection
        if ($token->revoked_at !== null) {
            event(new RevokedTokenReused($token));

            $token->deleteAllTokensFromSameGroup();

            return false;
        }

        $isValid = $token->expires_at === null || $token->expires_at->gt(now());

        if (is_callable(TokenAuth::$authTokenAuthenticationCallback)) {
            $isValid = (bool) (TokenAuth::$authTokenAuthenticationCallback)(
                $token,
                $isValid
            );
        }

        return $isValid;
    }

    /**
     * Determine if the tokenable model supports API tokens.
     *
     * @param mixed $tokenable
     * @return bool
     */
    protected function supportsTokens($tokenable = null) {
        return $tokenable &&
            in_array(
                HasAuthTokens::class,
                class_uses_recursive(get_class($tokenable))
            );
    }
}
