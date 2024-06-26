<?php

namespace TokenAuth\Tests\Unit\Concerns;

use Mockery;
use Mockery\MockInterface;
use TokenAuth\Concerns\AuthTokenHelpers;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Tests\TestCase;

/**
 * @covers \TokenAuth\Concerns\AuthTokenHelpers
 *
 * @uses \TokenAuth\Enums\TokenType
 * @uses \TokenAuth\Facades\TokenAuth
 * @uses \TokenAuth\TokenAuthManager
 * @uses \TokenAuth\TokenAuthServiceProvider
 */
class AuthTokenHelpersTest extends TestCase {
    private TokenTestClass|MockInterface $token;

    protected function setUp(): void {
        parent::setUp();

        /**
         * @var TokenTestClass|MockInterface
         */
        $this->token = Mockery::mock(TokenTestClass::class);
        $this->token->makePartial();
    }

    public function testHasAbilityReturnsTrueIfTokenHasAbility(): void {
        $abilities = ['foo', 'bar'];

        $this->token->shouldReceive('getAbilities')->andReturn($abilities);

        $this->assertTrue($this->token->hasAbility($abilities[0]));
    }

    public function testHasAbilityReturnsTrueIfTokenHasAllAbilities(): void {
        $abilities = ['*'];

        $this->token->shouldReceive('getAbilities')->andReturn($abilities);

        $this->assertTrue($this->token->hasAbility('baz'));
    }

    public function testHasAbilityReturnsFalseIfTokenDoesNotHaveAbility(): void {
        $abilities = ['foo', 'bar'];

        $this->token->shouldReceive('getAbilities')->andReturn($abilities);

        $this->assertFalse($this->token->hasAbility('baz'));
    }

    public function testIsRevokedReturnsTrueIfTokenIsRevoked(): void {
        $this->token
            ->shouldReceive('getRevokedAt')
            ->andReturn(now()->subMinute());

        $this->assertTrue($this->token->isRevoked());
    }

    public function testIsRevokedReturnsFalseIfTokenIsNotRevoked(): void {
        $this->token->shouldReceive('getRevokedAt')->andReturn(null);

        $this->assertFalse($this->token->isRevoked());
    }

    public function testIsExpiredReturnsTrueIfTokenIsExpired(): void {
        $this->token
            ->shouldReceive('getExpiresAt')
            ->andReturn(now()->subMinute());

        $this->assertTrue($this->token->isExpired());
    }

    public function testIsExpiredReturnsFalseIfTokenIsNotYetExpired(): void {
        $this->token
            ->shouldReceive('getExpiresAt')
            ->andReturn(now()->addMinute());

        $this->assertFalse($this->token->isExpired());
    }

    public function testIsExpiredReturnsFalseIfTokenHasNoExpiration(): void {
        $this->token->shouldReceive('getExpiresAt')->andReturn(null);

        $this->assertFalse($this->token->isExpired());
    }

    public function testIsActiveReturnsTrueIfTokenIsNotRevokedAndNotExpired(): void {
        $this->token->shouldReceive('isRevoked')->andReturn(false);

        $this->token->shouldReceive('isExpired')->andReturn(false);

        $this->assertTrue($this->token->isActive());
    }

    public function testIsActiveReturnsFalseIfTokenIsRevoked(): void {
        $this->token->shouldReceive('isRevoked')->andReturn(true);

        $this->token->shouldReceive('isExpired')->andReturn(false);

        $this->assertFalse($this->token->isActive());
    }

    public function testIsActiveReturnsFalseIfTokenIsExpired(): void {
        $this->token->shouldReceive('isRevoked')->andReturn(false);

        $this->token->shouldReceive('isExpired')->andReturn(true);

        $this->assertFalse($this->token->isActive());
    }

    public function testHashTokenReturnsAHashedString(): void {
        $token = 'test-token';

        $this->assertNotEquals($token, $this->token->hashToken($token));
    }
}

abstract class TokenTestClass implements AuthTokenContract {
    use AuthTokenHelpers;
}
