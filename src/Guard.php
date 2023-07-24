<?php

namespace TokenAuth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Events\RevokedTokenReused;
use TokenAuth\Events\TokenAuthenticated;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Concerns\HasAuthTokens;

class Guard {
    protected TokenType $expectedTokenType;

    public function __construct(TokenType $expectedTokenType) {
        $this->expectedTokenType = $expectedTokenType;
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

        $this->setAuthenticableTokenIfPossible($authenticable, $authToken);

        event(new TokenAuthenticated($authToken));

        return $authenticable;
    }

    /**
     * Get the token from the request
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    protected function getTokenFromRequest(Request $request): ?string {
        if (is_callable(TokenAuth::getAuthTokenRetrievalCallback())) {
            $token = call_user_func_array(
                TokenAuth::getAuthTokenRetrievalCallback(),
                [$request, $this->expectedTokenType]
            );

            if ($token === null) {
                return null;
            }

            return (string) $token;
        }

        return $request->bearerToken();
    }

    /**
     * Find the appropriate instance of the given token
     * @param string $token
     * @return \TokenAuth\Contracts\AuthTokenContract|null
     */
    protected function getTokenInstance(string $token): ?AuthTokenContract {
        return $this->getAuthTokenClass()::find(
            $this->expectedTokenType,
            $token,
            active: true
        );
    }

    /**
     * Determine if the provided token is valid
     * @param \TokenAuth\Contracts\AuthTokenContract|null $token
     * @return bool
     */
    protected function isValidToken($token): bool {
        if ($token === null) {
            return false;
        }

        // reuse-detection
        if ($token->isRevoked()) {
            event(new RevokedTokenReused($token));

            $this->getAuthTokenClass()::deleteTokensFromGroup($token->group_id);

            return false;
        }

        $isValid = $token->isActive();

        if (is_callable(TokenAuth::getAuthTokenAuthenticationCallback())) {
            $isValid = (bool) call_user_func_array(
                TokenAuth::getAuthTokenAuthenticationCallback(),
                [$token, $isValid]
            );
        }

        return $isValid;
    }

    /**
     * Set the token on the authenticable if possible
     * @param \Illuminate\Contracts\Auth\Authenticatable|\TokenAuth\Concerns\HasAuthTokens $authenticatable
     * @param \TokenAuth\Contracts\AuthTokenContract $token
     * @return void
     */
    private function setAuthenticableTokenIfPossible(
        Authenticatable $authenticatable,
        AuthTokenContract $token
    ): void {
        if (
            !in_array(
                HasAuthTokens::class,
                class_uses_recursive(get_class($authenticatable))
            )
        ) {
            return;
        }

        $authenticatable->withToken($token);
    }

    /**
     * @return \TokenAuth\Contracts\AuthTokenContract
     */
    private function getAuthTokenClass(): string {
        return TokenAuth::getAuthTokenClass();
    }
}
