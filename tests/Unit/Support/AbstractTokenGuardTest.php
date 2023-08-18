<?php

namespace TokenAuth\Tests\Unit\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Events\RevokedTokenReused;
use TokenAuth\Events\TokenAuthenticated;
use TokenAuth\Support\AbstractTokenGuard;

/**
 * @covers \TokenAuth\Support\AbstractTokenGuard
 * @covers \TokenAuth\Events\TokenAuthenticated
 * @covers \TokenAuth\Events\RevokedTokenReused
 */
class AbstractTokenGuardTest extends TestCase {
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
        $guard = new class (TokenType::ACCESS) extends
            AbstractTokenGuardTestClass {
            public function user(): ?Authenticatable {
                return Mockery::mock(Authenticatable::class);
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
        $guard = new class (TokenType::ACCESS) extends
            AbstractTokenGuardTestClass {
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
         * @var AuthTokenContract|MockInterface
         */
        $testTokenInstance = Mockery::mock(AuthTokenContract::class);

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
            ->andReturn($testTokenInstance);

        $guard
            ->shouldReceive('isValidToken')
            ->with($testTokenInstance)
            ->once()
            ->andReturnTrue();

        $testTokenInstance
            ->shouldReceive('getAuthenticatable')
            ->once()
            ->andReturn($testUser);

        Event::fakeFor(function () use (
            $guard,
            $requestMock,
            $testTokenInstance,
            $testUser
        ) {
            $guard->setRequest($requestMock);
            $this->assertSame($testUser, $guard->resolveUser());
            $this->assertSame($testTokenInstance, $guard->getCurrentToken());

            Event::assertDispatched(TokenAuthenticated::class, function (
                TokenAuthenticated $event
            ) use ($testTokenInstance) {
                return $event->token === $testTokenInstance;
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

        /**
         * @var AuthTokenContract|MockInterface
         */
        $testTokenInstance = Mockery::mock(AuthTokenContract::class);

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
            ->andReturn($testTokenInstance);

        $guard
            ->shouldReceive('isValidToken')
            ->with($testTokenInstance)
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

    public function testIsValidTokenReturnsTrueIfTheTokenIsActive(): void {
        $guard = $this->createGuard();

        /**
         * @var AuthTokenContract|MockInterface
         */
        $token = Mockery::mock(AuthTokenContract::class);

        $token
            ->shouldReceive('isRevoked')
            ->once()
            ->andReturnFalse();
        $token
            ->shouldReceive('isActive')
            ->once()
            ->andReturnTrue();

        $this->assertTrue($guard->isValidToken($token));
    }

    public function testIsValidTokenReturnsFalseIfTokenIsNull(): void {
        $guard = $this->createGuard();

        $this->assertFalse($guard->isValidToken(null));
    }

    public function testIsValidTokenHandlesReuseAndReturnsFalseIfTokenIsRevoked(): void {
        $guard = $this->createGuard();

        /**
         * @var AuthTokenContract|MockInterface
         */
        $token = Mockery::mock(AuthTokenContract::class);

        $token
            ->shouldReceive('isRevoked')
            ->once()
            ->andReturnTrue();

        $guard
            ->shouldReceive('handleDetectedReuse')
            ->with($token)
            ->once();

        Event::fakeFor(function () use ($guard, $token) {
            $this->assertFalse($guard->isValidToken($token));

            Event::assertDispatched(RevokedTokenReused::class, function (
                RevokedTokenReused $event
            ) use ($token) {
                return $event->token === $token;
            });
        });
    }

    public function testIsValidTokenReturnsFalseIfTokenIsNotActive(): void {
        $guard = $this->createGuard();

        /**
         * @var AuthTokenContract|MockInterface
         */
        $token = Mockery::mock(AuthTokenContract::class);

        $token
            ->shouldReceive('isRevoked')
            ->once()
            ->andReturnFalse();
        $token
            ->shouldReceive('isActive')
            ->once()
            ->andReturnFalse();

        $this->assertFalse($guard->isValidToken($token));
    }

    public function testGetCurrentTokenReturnsTheCurrentToken(): void {
        $guard = $this->createGuard();

        /**
         * @var AuthTokenContract|MockInterface
         */
        $token = Mockery::mock(AuthTokenContract::class);

        $guard->setCurrentToken($token);

        $this->assertSame($token, $guard->getCurrentToken());
    }

    public function testSetCurrentTokenSetsTheCurrentToken(): void {
        $guard = $this->createGuard();

        /**
         * @var AuthTokenContract|MockInterface
         */
        $token = Mockery::mock(AuthTokenContract::class);

        $guard->setCurrentToken($token);

        $this->assertSame($token, $guard->getCurrentToken());
    }

    private function createGuard(
        TokenType $type = TokenType::ACCESS
    ): AbstractTokenGuardTestClass|MockInterface {
        /**
         * @var AbstractTokenGuardTestClass|MockInterface
         */
        $guard = Mockery::mock(AbstractTokenGuardTestClass::class, [$type]);
        $guard->makePartial();

        return $guard;
    }
}

abstract class AbstractTokenGuardTestClass extends AbstractTokenGuard {
    public function resolveUser(): ?Authenticatable {
        return parent::resolveUser();
    }

    public function getRequest(): ?Request {
        return $this->request;
    }

    public function getTokenFromRequest(Request $request): ?string {
        return parent::getTokenFromRequest($request);
    }

    abstract public function getTokenInstance(
        string $token
    ): ?AuthTokenContract;

    public function isValidToken(?AuthTokenContract $token): bool {
        return parent::isValidToken($token);
    }

    abstract public function handleDetectedReuse(
        AuthTokenContract $token
    ): void;
}
