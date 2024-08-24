<?php

namespace TokenAuth\Tests\Unit\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Events\RevokedTokenReused;
use TokenAuth\Events\TokenAuthenticated;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Support\TokenGuard;
use TokenAuth\Tests\TestCase;
use TokenAuth\TokenAuthManager;
use TokenAuth\TokenAuthServiceProvider;

#[CoversClass(TokenGuard::class)]
#[CoversClass(TokenAuthenticated::class)]
#[CoversClass(RevokedTokenReused::class)]
#[UsesClass(TokenType::class)]
#[UsesClass(TokenAuth::class)]
#[UsesClass(TokenAuthManager::class)]
#[UsesClass(TokenAuthServiceProvider::class)]
class TokenGuardTest extends TestCase {
    private AuthTokenContract|MockInterface $tokenMock;

    public function setUp(): void {
        parent::setUp();

        $tokenMock = Mockery::mock(AuthTokenContract::class);

        $tokenAuthMock = TokenAuth::partialMock();
        $tokenAuthMock
            ->shouldReceive('getAuthTokenClass')
            ->andReturn($tokenMock::class);

        $this->tokenMock = $tokenMock;
    }

    public function testSetRequestSetsTheRequest(): void {
        $guard = $this->createGuard();

        /**
         * @var Request|MockInterface
         */
        $requestMock = Mockery::mock(Request::class);

        $guard->setRequest($requestMock);

        $this->assertSame($requestMock, $guard->getRequest());
    }

    public function testValidateReturnsTrueIfRequestInCredentialsResolvesAUser(): void {
        $guard = new class (TokenType::ACCESS) extends TokenGuardTestClass {
            public function user(): ?Authenticatable {
                /** @var Authenticatable|MockInterface  */
                $authenticatable = Mockery::mock(Authenticatable::class);

                return $authenticatable;
            }

            public function getTokenInstance(
                string $token
            ): ?AuthTokenContract {
                return null;
            }

            public function handleDetectedReuse(
                AuthTokenContract $token
            ): void {
            }
        };

        $this->assertTrue(
            $guard->validate([
                'request' => Mockery::mock(Request::class),
            ])
        );
    }

    public function testValidateReturnsFalseIfRequestInCredentialsDoesNotResolveAUser(): void {
        $guard = new class (TokenType::ACCESS) extends TokenGuardTestClass {
            public function user(): ?Authenticatable {
                return null;
            }

            public function getTokenInstance(
                string $token
            ): ?AuthTokenContract {
                return null;
            }

            public function handleDetectedReuse(
                AuthTokenContract $token
            ): void {
            }
        };

        $this->assertFalse(
            $guard->validate([
                'request' => Mockery::mock(Request::class),
            ])
        );
    }

    public function testUserReturnsResolvedUser(): void {
        $guard = $this->createGuard();

        /**
         * @var Authenticatable|MockInterface
         */
        $testUser = Mockery::mock(Authenticatable::class);

        $guard
            ->shouldReceive('resolveUser')
            ->once()
            ->andReturn($testUser);

        $this->assertSame($testUser, $guard->user());
    }

    public function testUserReturnsCachedUserIfFetchedBefore(): void {
        $guard = $this->createGuard();

        /**
         * @var Authenticatable|MockInterface
         */
        $testUser = Mockery::mock(Authenticatable::class);

        $guard
            ->shouldReceive('resolveUser')
            ->once()
            ->andReturn($testUser);

        $this->assertSame($testUser, $guard->user());
        $this->assertSame($testUser, $guard->user());
    }

    public function testResolveUserWithValidTokenHandlesAuthenticationAndReturnsAuthenticatable(): void {
        $testToken = 'test-token';

        /**
         * @var Authenticatable|MockInterface
         */
        $testUser = Mockery::mock(Authenticatable::class);

        $guard = $this->createGuard();

        /**
         * @var Request|MockInterface
         */
        $requestMock = Mockery::mock(Request::class);

        $guard
            ->shouldReceive('getTokenFromRequest')
            ->with($requestMock)
            ->once()
            ->andReturn($testToken);

        $guard
            ->shouldReceive('getTokenInstance')
            ->with($testToken)
            ->once()
            ->andReturn($this->tokenMock);

        $guard
            ->shouldReceive('isValidToken')
            ->with($this->tokenMock)
            ->once()
            ->andReturnTrue();

        $this->tokenMock
            ->shouldReceive('getAuthenticatable')
            ->once()
            ->andReturn($testUser);

        Event::fakeFor(function () use ($guard, $requestMock, $testUser) {
            $guard->setRequest($requestMock);
            $this->assertSame($testUser, $guard->resolveUser());
            $this->assertSame($this->tokenMock, $guard->getCurrentToken());

            Event::assertDispatched(TokenAuthenticated::class, function (
                TokenAuthenticated $event
            ) {
                return $event->token === $this->tokenMock;
            });
        });
    }

    public function testResolveUserWithNoTokenReturnsNull(): void {
        $guard = $this->createGuard();

        /**
         * @var Request|MockInterface
         */
        $requestMock = Mockery::mock(Request::class);

        $guard
            ->shouldReceive('getTokenFromRequest')
            ->with($requestMock)
            ->once()
            ->andReturnNull();

        $guard->setRequest($requestMock);

        $this->assertNull($guard->resolveUser());
    }

    public function testResolveUserWithInvalidTokenReturnsNull(): void {
        $testToken = 'test-token';

        $guard = $this->createGuard();

        /**
         * @var Request|MockInterface
         */
        $requestMock = Mockery::mock(Request::class);

        $guard
            ->shouldReceive('getTokenFromRequest')
            ->with($requestMock)
            ->once()
            ->andReturn($testToken);

        $guard
            ->shouldReceive('getTokenInstance')
            ->with($testToken)
            ->once()
            ->andReturn($this->tokenMock);

        $guard
            ->shouldReceive('isValidToken')
            ->with($this->tokenMock)
            ->once()
            ->andReturnFalse();

        $guard->setRequest($requestMock);

        $this->assertNull($guard->resolveUser());
    }

    public function testGetTokenFromRequestReturnsTheBearerToken(): void {
        $testBearerToken = 'test-bearer-token';

        $guard = $this->createGuard();

        /**
         * @var Request|MockInterface
         */
        $requestMock = Mockery::mock(Request::class);
        $requestMock
            ->shouldReceive('bearerToken')
            ->once()
            ->andReturn($testBearerToken);

        $this->assertEquals(
            $testBearerToken,
            $guard->getTokenFromRequest($requestMock)
        );
    }

    #[DataProvider('tokenTypeProvider')]
    public function testGetTokenInstanceReturnsTheExpectedTokenWithTheExpectedType(
        TokenType $tokenType
    ): void {
        $testToken = 'test-token';

        $this->tokenMock
            ->shouldReceive('find')
            ->with($tokenType, $testToken, true)
            ->andReturn($this->tokenMock);

        $guard = $this->createGuard($tokenType);

        $this->assertSame(
            $this->tokenMock,
            $guard->getTokenInstance($testToken)
        );
    }

    public function testIsValidTokenReturnsTrueIfTheTokenIsActive(): void {
        $guard = $this->createGuard();

        $this->tokenMock
            ->shouldReceive('isRevoked')
            ->once()
            ->andReturnFalse();
        $this->tokenMock
            ->shouldReceive('isActive')
            ->once()
            ->andReturnTrue();

        $this->assertTrue($guard->isValidToken($this->tokenMock));
    }

    public function testIsValidTokenReturnsFalseIfTokenIsNull(): void {
        $guard = $this->createGuard();

        $this->assertFalse($guard->isValidToken(null));
    }

    public function testIsValidTokenHandlesReuseAndReturnsFalseIfTokenIsRevoked(): void {
        $guard = $this->createGuard();

        $this->tokenMock
            ->shouldReceive('isRevoked')
            ->once()
            ->andReturnTrue();

        $guard
            ->shouldReceive('handleDetectedReuse')
            ->with($this->tokenMock)
            ->once();

        Event::fakeFor(function () use ($guard) {
            $this->assertFalse($guard->isValidToken($this->tokenMock));

            Event::assertDispatched(RevokedTokenReused::class, function (
                RevokedTokenReused $event
            ) {
                return $event->token === $this->tokenMock;
            });
        });
    }

    public function testIsValidTokenReturnsFalseIfTokenIsNotActive(): void {
        $guard = $this->createGuard();

        $this->tokenMock
            ->shouldReceive('isRevoked')
            ->once()
            ->andReturnFalse();
        $this->tokenMock
            ->shouldReceive('isActive')
            ->once()
            ->andReturnFalse();

        $this->assertFalse($guard->isValidToken($this->tokenMock));
    }

    public function testHandleDetectedReuseDeletesAllTokensFromTheSameGroup(): void {
        $testGroupId = 111;

        $this->tokenMock
            ->shouldReceive('getGroupId')
            ->once()
            ->andReturn($testGroupId);

        $this->tokenMock
            ->shouldReceive('deleteTokensFromGroup')
            ->with($testGroupId)
            ->once();

        $guard = $this->createGuard();

        $guard->handleDetectedReuse($this->tokenMock);
    }

    public function testGetCurrentTokenReturnsTheCurrentToken(): void {
        $user = Mockery::mock(Authenticatable::class);

        $this->tokenMock
            ->shouldReceive('getAuthenticatable')
            ->once()
            ->andReturn($user);

        $guard = $this->createGuard();

        $guard->setCurrentToken($this->tokenMock);

        $this->assertSame($this->tokenMock, $guard->getCurrentToken());
    }

    public function testSetCurrentTokenSetsTheCurrentToken(): void {
        $user = Mockery::mock(Authenticatable::class);

        $this->tokenMock
            ->shouldReceive('getAuthenticatable')
            ->once()
            ->andReturn($user);

        $guard = $this->createGuard();

        $guard->setCurrentToken($this->tokenMock);

        $this->assertSame($this->tokenMock, $guard->getCurrentToken());
    }

    private function createGuard(
        TokenType $type = TokenType::ACCESS
    ): TokenGuardTestClass|MockInterface {
        /**
         * @var TokenGuardTestClass|MockInterface
         */
        $guard = Mockery::mock(TokenGuardTestClass::class, [$type]);
        $guard->makePartial();

        return $guard;
    }
}

abstract class TokenGuardTestClass extends TokenGuard {
    public function resolveUser(): ?Authenticatable {
        return parent::resolveUser();
    }

    public function getRequest(): ?Request {
        return $this->request;
    }

    public function getTokenFromRequest(Request $request): ?string {
        return parent::getTokenFromRequest($request);
    }

    public function getTokenInstance(string $token): ?AuthTokenContract {
        return parent::getTokenInstance($token);
    }

    public function isValidToken(?AuthTokenContract $token): bool {
        return parent::isValidToken($token);
    }

    public function handleDetectedReuse(AuthTokenContract $token): void {
        parent::handleDetectedReuse($token);
    }
}
