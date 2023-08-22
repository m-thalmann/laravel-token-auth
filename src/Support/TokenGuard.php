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

class TokenGuard implements Guard {
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
    public function setRequest(Request $request): static {
        $this->request = $request;
        return $this;
    }

    public function validate(array $credentials = []): bool {
        return (new static($this->expectedTokenType))
            ->setRequest($credentials['request'])
            ->user() !== null;
    }

    public function user(): ?Authenticatable {
        if ($this->user !== null) {
            return $this->user;
        }

        return $this->user = $this->resolveUser();
    }

    /**
     * Resolve the user from the set request and set the currentToken.
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected function resolveUser(): ?Authenticatable {
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
    protected function getTokenInstance(string $token): ?AuthTokenContract {
        return TokenAuth::getAuthTokenClass()::find(
            $this->expectedTokenType,
            $token,
            mustBeActive: true
        );
    }

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
    protected function handleDetectedReuse(AuthTokenContract $token): void {
        TokenAuth::getAuthTokenClass()::deleteTokensFromGroup(
            $token->getGroupId()
        );
    }

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
