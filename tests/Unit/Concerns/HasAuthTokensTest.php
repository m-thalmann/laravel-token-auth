<?php

namespace TokenAuth\Tests\Unit\Concerns;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use TokenAuth\Concerns\HasAuthTokens;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Models\AuthToken;
use TokenAuth\Support\AuthTokenBuilder;
use TokenAuth\Support\NewAuthToken;
use TokenAuth\Tests\TestCase;
use TokenAuth\TokenAuthManager;
use TokenAuth\TokenAuthServiceProvider;
use Workbench\App\Models\User;

#[CoversTrait(HasAuthTokens::class)]
#[UsesClass(TokenType::class)]
#[UsesClass(TokenAuth::class)]
#[UsesClass(AuthToken::class)]
#[UsesClass(AuthTokenBuilder::class)]
#[UsesClass(NewAuthToken::class)]
#[UsesClass(TokenAuthManager::class)]
#[UsesClass(TokenAuthServiceProvider::class)]
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

    #[DataProvider('tokenTypeProvider')]
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
