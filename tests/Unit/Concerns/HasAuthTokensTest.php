<?php

namespace TokenAuth\Tests\Unit\Concerns;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use TokenAuth\Concerns\HasAuthTokens;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Models\AuthToken;
use TokenAuth\Tests\TestCase;
use TokenAuth\TokenAuthManager;
use Workbench\App\Models\User;

/**
 * @covers \TokenAuth\Concerns\HasAuthTokens
 *
 * @uses \TokenAuth\Concerns\AuthTokenHelpers
 * @uses \TokenAuth\Enums\TokenType
 * @uses \TokenAuth\Facades\TokenAuth
 * @uses \TokenAuth\Models\AuthToken
 * @uses \TokenAuth\Support\AuthTokenBuilder
 * @uses \TokenAuth\Support\NewAuthToken
 * @uses \TokenAuth\TokenAuthManager
 * @uses \TokenAuth\TokenAuthServiceProvider
 */
class HasAuthTokensTest extends TestCase {
    use LazilyRefreshDatabase;

    public function testTokensReturnsRelationshipTokens(): void {
        $user = $this->createTestUserWithTokens();

        $this->assertEquals(0, $user->tokens()->count());

        AuthToken::create(TokenType::ACCESS)
            ->setAuthenticatable($user)
            ->build();

        $this->assertEquals(1, $user->tokens()->count());
    }

    /**
     * @dataProvider tokenTypeProvider
     */
    public function testCreateTokenReturnsAnAuthTokenBuilderWithTheSetTypeAndAuthenticatable(
        TokenType $tokenType
    ): void {
        $user = $this->createTestUserWithTokens();

        /**
         * @var AuthTokenBuilderContract|MockInterface
         */
        $mockAuthTokenBuilder = Mockery::mock(AuthTokenBuilderContract::class);

        $mockAuthTokenBuilder
            ->shouldReceive('setAuthenticatable')
            ->with($user)
            ->once()
            ->andReturnSelf();

        /**
         * @var AuthTokenContract|MockInterface
         */
        $mockAuthTokenClass = Mockery::mock(AuthTokenContract::class);

        $mockAuthTokenClass
            ->shouldReceive('create')
            ->with($tokenType)
            ->once()
            ->andReturn($mockAuthTokenBuilder);

        /**
         * @var TokenAuthManager|MockInterface
         */
        $tokenAuthMock = TokenAuth::partialMock();

        $tokenAuthMock
            ->shouldReceive('getAuthTokenClass')
            ->once()
            ->andReturn($mockAuthTokenClass::class);

        $tokenBuilder = $user->createToken($tokenType);
    }

    protected function createTestUserWithTokens(): UserTestModel {
        return UserTestModel::forceCreate([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' =>
                '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ]);
    }
}

class UserTestModel extends User {
    use HasAuthTokens;
}
