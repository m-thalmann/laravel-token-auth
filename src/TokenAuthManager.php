<?php

namespace TokenAuth;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Mockery;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Contracts\TokenAuthManagerContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Models\AuthToken;
use TokenAuth\Support\TokenGuard;
use TokenAuth\Support\AuthTokenPairBuilder;

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
        if (!is_subclass_of($class, TokenGuard::class)) {
            throw new InvalidArgumentException(
                'The TokenGuard class must extend from ' . TokenGuard::class
            );
        }

        $this->tokenGuardClass = $class;
    }

    public function createTokenPair(
        Authenticatable $authenticatable,
        bool $generateGroupId = true
    ): AuthTokenPairBuilder {
        $accessToken = $this->authTokenClass::create(TokenType::ACCESS);
        $refreshToken = $this->authTokenClass::create(TokenType::REFRESH);

        $tokenPair = (new AuthTokenPairBuilder(
            $accessToken,
            $refreshToken
        ))->setAuthenticatable($authenticatable);

        if ($generateGroupId) {
            $tokenPair->setGroupId(
                $this->authTokenClass::generateGroupId($authenticatable)
            );
        }

        return $tokenPair;
    }

    public function rotateTokenPair(
        AuthTokenContract $refreshToken,
        bool $deleteAccessTokens = true
    ): AuthTokenPairBuilder {
        $pairBuilder = AuthTokenPairBuilder::fromToken($refreshToken);

        return $pairBuilder->beforeBuildSave(function () use (
            $refreshToken,
            $deleteAccessTokens
        ) {
            $refreshToken->revoke()->store();

            if ($deleteAccessTokens) {
                $this->authTokenClass::deleteTokensFromGroup(
                    $refreshToken->getGroupId(),
                    TokenType::ACCESS
                );
            }
        });
    }

    public function currentToken(): ?AuthTokenContract {
        $guard = app('auth')->guard();

        if ($guard instanceof TokenGuard) {
            return $guard->getCurrentToken();
        }

        return null;
    }

    public function actingAs(
        ?Authenticatable $user,
        array $abilities = [],
        TokenType $tokenType = TokenType::ACCESS
    ): ?AuthTokenContract {
        if ($user === null) {
            app('auth')->forgetGuards();

            return null;
        }

        /**
         * @var \Mockery\MockInterface|\Mockery\LegacyMockInterface|AuthTokenContract
         */
        $token = Mockery::mock($this->getAuthTokenClass());
        $token->shouldIgnoreMissing(false);

        if (in_array('*', $abilities)) {
            $token
                ->shouldReceive('hasAbility')
                ->withAnyArgs()
                ->andReturn(true);
        } else {
            foreach ($abilities as $ability) {
                $token
                    ->shouldReceive('hasAbility')
                    ->with($ability)
                    ->andReturn(true);
            }
        }

        $token->shouldReceive('getAbilities')->andReturn($abilities);

        $token->shouldReceive('getType')->andReturn($tokenType);
        $token->shouldReceive('getAuthenticatable')->andReturn($user);
        $token->shouldReceive('isActive')->andReturn(true);

        /**
         * @var \TokenAuth\Support\TokenGuard
         */
        $tokenGuard = auth()->guard($tokenType->getGuardName());
        $tokenGuard->setUser($user);
        $tokenGuard->setCurrentToken($token);

        app('auth')->shouldUse($tokenType->getGuardName());

        return $token;
    }
}
