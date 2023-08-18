<?php

namespace TokenAuth\Tests\Unit\Support;

use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Support\TokenGuard;
use TokenAuth\Tests\Helpers\HasTokenTypeProvider;
use TokenAuth\Tests\Helpers\UsesPackageProvider;

/**
 * @covers \TokenAuth\Support\TokenGuard
 *
 * @uses \TokenAuth\Enums\TokenType
 * @uses \TokenAuth\Facades\TokenAuth
 * @uses \TokenAuth\Support\AbstractTokenGuard
 * @uses \TokenAuth\TokenAuthManager
 * @uses \TokenAuth\TokenAuthServiceProvider
 */
class TokenGuardTest extends TestCase {
    use HasTokenTypeProvider, UsesPackageProvider;

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

    /**
     * @dataProvider tokenTypeProvider
     */
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

class TokenGuardTestClass extends TokenGuard {
    public function getTokenInstance(string $token): ?AuthTokenContract {
        return parent::getTokenInstance($token);
    }

    public function handleDetectedReuse(AuthTokenContract $token): void {
        parent::handleDetectedReuse($token);
    }
}
