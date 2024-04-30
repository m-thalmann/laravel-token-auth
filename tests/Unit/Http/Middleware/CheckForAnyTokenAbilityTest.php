<?php

namespace TokenAuth\Tests\Unit\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Exceptions\MissingTokenAbilitiesException;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Http\Middleware\CheckForAnyTokenAbility;
use TokenAuth\Tests\TestCase;
use TokenAuth\TokenAuthManager;

/**
 * @covers \TokenAuth\Http\Middleware\CheckForAnyTokenAbility
 * @covers \TokenAuth\Exceptions\MissingTokenAbilitiesException
 *
 * @uses \TokenAuth\Enums\TokenType
 * @uses \TokenAuth\Facades\TokenAuth
 * @uses \TokenAuth\TokenAuthManager
 * @uses \TokenAuth\TokenAuthServiceProvider
 */
class CheckForAnyTokenAbilityTest extends TestCase {
    private CheckForAnyTokenAbility $middleware;
    private TokenAuthManager|MockInterface $tokenAuthMock;

    protected function setUp(): void {
        parent::setUp();

        $this->middleware = new CheckForAnyTokenAbility();
        $this->tokenAuthMock = TokenAuth::partialMock();
    }

    public function testHandleResolvesIfAuthenticatedTokenHasOneOfTheAbilities(): void {
        $tokenAbilities = ['ability1', 'ability2'];
        $testResponse = 'test response';
        $testNext = fn() => $testResponse;

        $this->createToken($tokenAbilities);

        $checkAbilities = ['not an ability', $tokenAbilities[0]];

        $response = $this->middleware->handle(
            new Request(),
            $testNext,
            ...$checkAbilities
        );

        $this->assertEquals($testResponse, $response);
    }

    public function testHandleThrowsAuthenticationExceptionIfNoTokenIsAuthenticated(): void {
        $this->expectException(AuthenticationException::class);

        $this->tokenAuthMock
            ->shouldReceive('currentToken')
            ->once()
            ->andReturnNull();

        $this->middleware->handle(new Request(), fn() => null, 'ability');
    }

    public function testHandleThrowsMissingTokenAbilitiesExceptionIfAuthenticatedTokenDoesNotHaveAnyOfTheAbilities(): void {
        $tokenAbilities = ['ability1', 'ability2'];

        $this->createToken($tokenAbilities);

        $checkAbilities = ['not an ability', 'also not an ability'];

        try {
            $this->middleware->handle(
                new Request(),
                fn() => null,
                ...$checkAbilities
            );

            $this->fail(
                'Failed asserting that exception of type "' .
                    MissingTokenAbilitiesException::class .
                    '" is thrown.'
            );
        } catch (MissingTokenAbilitiesException $e) {
            $this->assertSame($checkAbilities, $e->missingAbilities);
        }
    }

    private function createToken(
        array $abilities
    ): AuthTokenContract|MockInterface {
        /**
         * @var AuthTokenContract|MockInterface $token
         */
        $token = Mockery::mock(AuthTokenContract::class);

        $token
            ->shouldReceive('hasAbility')
            ->andReturnUsing(function (string $ability) use ($abilities) {
                return in_array($ability, $abilities);
            });

        $this->tokenAuthMock
            ->shouldReceive('currentToken')
            ->once()
            ->andReturn($token);

        return $token;
    }
}
