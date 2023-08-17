<?php

namespace TokenAuth\Support;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Events\RevokedTokenReused;
use TokenAuth\Events\TokenAuthenticated;
use TokenAuth\Facades\TokenAuth;

abstract class AbstractTokenGuard implements Guard {
    use GuardHelpers;

    protected Request $request;

    protected ?AuthTokenContract $currentToken = null;

    public function __construct(
        protected readonly TokenType $expectedTokenType
    ) {
    }

    /**
     * Set the current request instance.
     * @param \Illuminate\Http\Request $request
     * @return $this
     */
    final public function setRequest(Request $request) {
        $this->request = $request;

        return $this;
    }

    final public function validate(array $credentials = []) {
        return (new static($this->expectedTokenType))
            ->setRequest($credentials['request'])
            ->user() !== null;
    }

    final public function user(): ?Authenticatable {
        if ($this->user !== null) {
            return $this->user;
        }

        return $this->user = $this->resolveUser();
    }

    /**
     * Resolve the user from the set request.
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function resolveUser(): ?Authenticatable {
        $this->currentToken = null;

        $token = $this->getTokenFromRequest($this->request);

        if ($token === null) {
            return null;
        }

        $authToken = $this->getTokenInstance($token);

        if (!$this->isValidToken($authToken)) {
            return null;
        }

        $authenticatable = $authToken->getAuthenticatable();

        $this->currentToken = $authToken;

        event(new TokenAuthenticated($authToken));

        return $authenticatable;
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
     * Find the appropriate instance of the given token.
     * Should only return a token if it is of the expected type.
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
     * Get the authenticated token instance
     * @return \TokenAuth\Contracts\AuthTokenContract|null
     */
    public function getCurrentToken(): ?AuthTokenContract {
        return $this->currentToken;
    }

    /**
     * Set the authenticated token instance
     * @param \TokenAuth\Contracts\AuthTokenContract|null $token
     */
    public function setCurrentToken(?AuthTokenContract $token): void {
        $this->currentToken = $token;
    }
}
