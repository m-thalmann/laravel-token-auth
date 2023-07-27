<?php

namespace TokenAuth\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Events\RevokedTokenReused;
use TokenAuth\Events\TokenAuthenticated;
use TokenAuth\Facades\TokenAuth;

abstract class AbstractTokenGuard {
    /**
     * The class / model to use for the auth token.
     * Must implement the `AuthTokenContract`
     */
    protected string $authTokenClass;

    public function __construct(
        protected readonly TokenType $expectedTokenType
    ) {
        $this->authTokenClass = TokenAuth::getAuthTokenClass();
    }

    /**
     * Retrieve the authenticated user for the incoming request
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function __invoke(Request $request): ?Authenticatable {
        $token = $this->getTokenFromRequest($request);

        if ($token === null) {
            return null;
        }

        $authToken = $this->getTokenInstance($token);

        if (!$this->isValidToken($authToken)) {
            return null;
        }

        $authenticable = $authToken->getAuthenticable();

        $this->maybeSetTokenOnAuthenticable($authenticable, $authToken);

        event(new TokenAuthenticated($authToken));

        return $authenticable;
    }

    /**
     * Get the plaintext token from the request
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    protected function getTokenFromRequest(Request $request): ?string {
        return $request->bearerToken();
    }

    /**
     * Find the appropriate instance of the given token
     * @param string $token
     * @return \TokenAuth\Contracts\AuthTokenContract|null
     */
    abstract protected function getTokenInstance(
        string $token
    ): ?AuthTokenContract;

    /**
     * Determine if the provided token is valid
     * @param \TokenAuth\Contracts\AuthTokenContract|null $token
     * @return bool
     */
    protected function isValidToken(?AuthTokenContract $token): bool {
        if ($token === null) {
            return false;
        }

        // reuse-detection
        if ($token->isRevoked()) {
            event(new RevokedTokenReused($token));

            $this->handleDetectedReuse($token);

            return false;
        }

        return $token->isActive();
    }

    /**
     * Is called when a token reuse is detected (the token-type does not matter)
     * @param AuthTokenContract $token
     * @return void
     */
    abstract protected function handleDetectedReuse(
        AuthTokenContract $token
    ): void;

    /**
     * Set the token on the authenticable if possible
     * @param \Illuminate\Contracts\Auth\Authenticatable|\TokenAuth\Concerns\HasAuthTokens $authenticatable
     * @param \TokenAuth\Contracts\AuthTokenContract $token
     * @return void
     */
    abstract protected function maybeSetTokenOnAuthenticable(
        Authenticatable $authenticatable,
        AuthTokenContract $token
    ): void;
}
