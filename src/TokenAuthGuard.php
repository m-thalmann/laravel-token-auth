<?php

namespace TokenAuth;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use InvalidArgumentException;
use TokenAuth\Events\RevokedTokenReused;
use TokenAuth\Events\TokenAuthenticated;
use TokenAuth\Traits\HasAuthTokens;

class TokenAuthGuard implements Guard {
    use GuardHelpers;

    /**
     * @var string The expected type of the token (refresh / access).
     */
    protected $tokenType;

    /**
     * @var \Illuminate\Http\Request The request instance.
     */
    protected $request;

    /**
     * @var bool Whether the authentication was tried before.
     */
    protected $triedAuthentication = false;

    /**
     * Create a new guard instance.
     *
     * @param \Illuminate\Contracts\Auth\Factory $auth
     * @param string $tokenType
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Contracts\Auth\UserProvider|null $provider
     *
     * @return void
     */
    public function __construct(
        string $tokenType,
        Request $request,
        UserProvider $provider = null
    ) {
        $this->tokenType = $tokenType;
        $this->request = $request;
        $this->provider = $provider;
    }

    /**
     * Validate a user's credentials.
     *
     * @param array $credentials
     *
     * @return bool
     */
    public function validate(array $credentials = []) {
        return !is_null(
            (new static(
                $this->tokenType,
                $credentials['request'],
                $this->getProvider()
            ))->user()
        );
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user() {
        // If we've already tried to retrieve the user for the current request
        // we can just return it back immediately
        if (!is_null($this->user) || $this->triedAuthentication) {
            return $this->user;
        }

        $this->user = $this->authenticateFromRequest();
        $this->triedAuthentication = true;

        return $this->user;
    }

    /**
     * Retrieve the authenticated user for the incoming request.
     *
     * @return mixed
     */
    protected function authenticateFromRequest() {
        if ($token = $this->getTokenFromRequest($this->request)) {
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
     * @return \TokenAuth\Contracts\AuthTokenContract|\Illuminate\Database\Eloquent\Model
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

    /**
     * Set the current request instance.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return $this
     */
    public function setRequest(Request $request) {
        $this->request = $request;

        return $this;
    }
}
