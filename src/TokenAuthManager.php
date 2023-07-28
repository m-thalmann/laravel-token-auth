<?php

namespace TokenAuth;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Contracts\TokenAuthManagerContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Models\AuthToken;
use TokenAuth\Support\AbstractTokenGuard;
use TokenAuth\Support\TokenGuard;
use TokenAuth\Support\TokenPairBuilder;

class TokenAuthManager implements TokenAuthManagerContract {
    protected string $authTokenClass = AuthToken::class;
    protected string $tokenGuardClass = TokenGuard::class;

    public function getAuthTokenClass(): string {
        return $this->authTokenClass;
    }
    public function useAuthToken(string $class): void {
        if (!is_subclass_of($class, AuthTokenContract::class)) {
            throw new InvalidArgumentException(
                'The AuthToken class must implement ' . AuthTokenContract::class
            );
        }

        $this->authTokenClass = $class;
    }

    public function getTokenGuardClass(): string {
        return $this->tokenGuardClass;
    }
    public function useTokenGuard(string $class): void {
        if (!is_subclass_of($class, AbstractTokenGuard::class)) {
            throw new InvalidArgumentException(
                'The TokenGuard class must extend from ' .
                    AbstractTokenGuard::class
            );
        }

        $this->tokenGuardClass = $class;
    }

    public function createTokenPair(
        Authenticatable $authenticatable,
        bool $generateGroupId = true
    ): TokenPairBuilder {
        /**
         * @var AuthTokenBuilderContract
         */
        $accessToken = $this->authTokenClass::create(TokenType::ACCESS);
        /**
         * @var AuthTokenBuilderContract
         */
        $refreshToken = $this->authTokenClass::create(TokenType::REFRESH);

        $tokenPair = (new TokenPairBuilder(
            $accessToken,
            $refreshToken
        ))->setAuthenticable($authenticatable);

        if ($generateGroupId) {
            $tokenPair->setGroupId(
                $this->authTokenClass::generateGroupId($authenticatable)
            );
        }

        return $tokenPair;
    }
}
