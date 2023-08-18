<?php

namespace TokenAuth\Tests\Unit\Concerns;

use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use TokenAuth\Concerns\HasAuthTokens;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Models\AuthToken;
use TokenAuth\Tests\Helpers\HasTokenTypeProvider;
use TokenAuth\Tests\Helpers\Models\UserTestModel as BaseUserTestModel;
use TokenAuth\Tests\Helpers\UsesDatabase;
use TokenAuth\Tests\Helpers\UsesPackageProvider;
use TokenAuth\TokenAuthManager;

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
    use UsesPackageProvider, UsesDatabase, HasTokenTypeProvider;

    protected function setUp(): void {
        parent::setUp();

        $this->createUsersTable();
    }

    public function testTokensReturnsRelationshipTokens(): void {
        $user = $this->createTestUser();

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
        $user = $this->createTestUser();

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

    private function createTestUser() {
        return UserTestModel::forceCreate([
            'email' => 'test@example.com',
            'password' =>
                '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ]);
    }
}

class UserTestModel extends BaseUserTestModel {
    use HasAuthTokens;
}
