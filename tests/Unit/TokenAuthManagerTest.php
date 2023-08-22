<?php

namespace TokenAuth\Tests\Unit;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use stdClass;
use TokenAuth\Concerns\HasAuthTokens;
use TokenAuth\Enums\TokenType;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Models\AuthToken;
use TokenAuth\Support\AuthTokenPairBuilder;
use TokenAuth\Support\NewAuthToken;
use TokenAuth\Support\TokenGuard;
use TokenAuth\Tests\Helpers\HasTokenTypeProvider;
use TokenAuth\Tests\Helpers\UsesDatabase;
use TokenAuth\Tests\Helpers\UsesPackageProvider;
use TokenAuth\TokenAuthManager;

/**
 * @covers \TokenAuth\TokenAuthManager
 *
 * @uses \TokenAuth\TokenAuthServiceProvider
 * @uses \TokenAuth\Facades\TokenAuth
 * @uses \TokenAuth\Enums\TokenType
 * @uses \TokenAuth\Support\TokenGuard
 * @uses \TokenAuth\Support\AuthTokenPairBuilder
 * @uses \TokenAuth\Support\NewAuthToken
 * @uses \TokenAuth\Support\NewAuthTokenPair
 */
class TokenAuthManagerTest extends TestCase {
    use UsesPackageProvider, UsesDatabase, HasTokenTypeProvider;

    private TokenAuthManager $manager;

    public function setUp(): void {
        parent::setUp();

        $this->manager = new TokenAuthManager();
    }

    public function testGetAuthTokenClassReturnsDefaultClassIfNoOtherSet(): void {
        $this->assertEquals(
            AuthToken::class,
            $this->manager->getAuthTokenClass()
        );
    }

    public function testUseAuthTokenSetsTheAuthTokenClass(): void {
        $authTokenClass = new class extends AuthToken {};

        $this->manager->useAuthToken($authTokenClass::class);

        $this->assertEquals(
            $authTokenClass::class,
            $this->manager->getAuthTokenClass()
        );
    }

    public function testUseAuthTokenFailsIfClassDoesNotImplementAuthTokenContract(): void {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->useAuthToken(stdClass::class);
    }

    public function testGetTokenGuardClassReturnsDefaultClassIfNoOtherSet(): void {
        $this->assertEquals(
            TokenGuard::class,
            $this->manager->getTokenGuardClass()
        );
    }

    public function testUseTokenGuardSetsTheTokenGuardClass(): void {
        $this->manager->useTokenGuard(TokenGuardTestClass::class);

        $this->assertEquals(
            TokenGuardTestClass::class,
            $this->manager->getTokenGuardClass()
        );
    }

    public function testUseTokenGuardFailsIfClassDoesNotExtendTokenGuard(): void {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->useTokenGuard(stdClass::class);
    }

    public function testCreateTokenPairReturnsAnAuthTokenPairBuilder(): void {
        $user = Mockery::mock(Authenticatable::class);

        $generateGroupId = 1337;

        /**
         * @var AuthTokenBuilderContract|MockInterface
         */
        $builderMock = Mockery::mock(AuthTokenBuilderContract::class);

        // 2 times -> 1 for each token
        $builderMock
            ->shouldReceive('setAuthenticatable')
            ->with($user)
            ->twice();
        $builderMock
            ->shouldReceive('setGroupId')
            ->with($generateGroupId)
            ->twice();

        /**
         * @var AuthTokenContract|MockInterface
         */
        $authTokenClass = Mockery::mock(AuthTokenContract::class);

        $authTokenClass
            ->shouldReceive('create')
            ->with(TokenType::ACCESS)
            ->once()
            ->andReturn($builderMock);
        $authTokenClass
            ->shouldReceive('create')
            ->with(TokenType::REFRESH)
            ->once()
            ->andReturn($builderMock);

        $authTokenClass
            ->shouldReceive('generateGroupId')
            ->with($user)
            ->once()
            ->andReturn($generateGroupId);

        $this->manager->useAuthToken($authTokenClass::class);

        $tokenPairBuilder = $this->manager->createTokenPair($user);

        $this->assertInstanceOf(AuthTokenPairBuilder::class, $tokenPairBuilder);
    }

    public function testCreateTokenPairDoesNotGenerateGroupIdIfDisabled(): void {
        $user = Mockery::mock(Authenticatable::class);

        /**
         * @var AuthTokenBuilderContract|MockInterface
         */
        $builderMock = Mockery::mock(AuthTokenBuilderContract::class);

        $builderMock->shouldReceive('setAuthenticatable');
        $builderMock->shouldNotReceive('setGroupId');

        /**
         * @var AuthTokenContract|MockInterface
         */
        $authTokenClass = Mockery::mock(AuthTokenContract::class);

        $authTokenClass
            ->shouldReceive('create')
            ->twice()
            ->andReturn($builderMock);

        $this->manager->useAuthToken($authTokenClass::class);

        $tokenPairBuilder = $this->manager->createTokenPair(
            $user,
            generateGroupId: false
        );

        $this->assertInstanceOf(
            AuthTokenBuilderContract::class,
            $tokenPairBuilder
        );
    }

    public function testRotateTokenPairRotatesTheTokenAndReturnsAnAuthTokenPairBuilder(): void {
        $refreshTokenGroupId = 101;
        $user = Mockery::mock(Authenticatable::class);

        /**
         * @var AuthTokenBuilderContract|MockInterface
         */
        $authTokenBuilder = Mockery::mock(AuthTokenBuilderContract::class);
        $authTokenBuilder->shouldIgnoreMissing();

        /**
         * @var AuthTokenContract|MockInterface
         */
        $refreshToken = Mockery::mock(AuthTokenContract::class);

        $this->manager->useAuthToken($refreshToken::class);

        $authTokenBuilder
            ->shouldReceive('build')
            ->withAnyArgs()
            ->twice()
            ->andReturn(new NewAuthToken($refreshToken, ''));

        $refreshToken
            ->shouldReceive('create')
            ->with(TokenType::ACCESS)
            ->once()
            ->andReturn($authTokenBuilder);
        $refreshToken
            ->shouldReceive('create')
            ->with(TokenType::REFRESH)
            ->once()
            ->andReturn($authTokenBuilder);

        $refreshToken
            ->shouldReceive('getAuthenticatable')
            ->withNoArgs()
            ->once()
            ->andReturn($user);
        // once in "fromToken" and once when deleting access tokens
        $refreshToken
            ->shouldReceive('getGroupId')
            ->withNoArgs()
            ->twice()
            ->andReturn($refreshTokenGroupId);
        $refreshToken
            ->shouldReceive('getName')
            ->withNoArgs()
            ->once()
            ->andReturnNull();
        $refreshToken
            ->shouldReceive('getAbilities')
            ->withNoArgs()
            ->once()
            ->andReturn([]);

        $refreshToken
            ->shouldReceive('deleteTokensFromGroup')
            ->with($refreshTokenGroupId, TokenType::ACCESS)
            ->once();

        $refreshToken
            ->shouldReceive('revoke')
            ->withNoArgs()
            ->once();
        // twice from build, once from revoke
        $refreshToken
            ->shouldReceive('store')
            ->withNoArgs()
            ->times(3);

        $tokenPairBuilder = $this->manager->rotateTokenPair($refreshToken);

        $this->assertInstanceOf(AuthTokenPairBuilder::class, $tokenPairBuilder);

        $tokenPairBuilder->buildPair();
    }

    public function testRotateTokenPairDoesNotDeleteAccessTokensWhenDisabled(): void {
        /**
         * @var AuthTokenContract|MockInterface
         */
        $refreshToken = Mockery::mock(AuthTokenContract::class);

        $refreshToken->shouldIgnoreMissing();

        $this->manager->useAuthToken($refreshToken::class);

        /**
         * @var AuthTokenBuilderContract|MockInterface
         */
        $authTokenBuilder = Mockery::mock(AuthTokenBuilderContract::class);
        $authTokenBuilder->shouldIgnoreMissing();

        $authTokenBuilder
            ->shouldReceive('build')
            ->withAnyArgs()
            ->twice()
            ->andReturn(new NewAuthToken($refreshToken, ''));

        $refreshToken
            ->shouldReceive('create')
            ->withAnyArgs()
            ->andReturn($authTokenBuilder);

        $tokenPairBuilder = $this->manager->rotateTokenPair(
            $refreshToken,
            deleteAccessTokens: false
        );

        $this->assertInstanceOf(AuthTokenPairBuilder::class, $tokenPairBuilder);

        $refreshToken->shouldNotReceive('deleteTokensFromGroup');

        $tokenPairBuilder->buildPair();
    }

    public function testCurrentTokenReturnsTheAuthenticationTokenFromTheCurrentGuard(): void {
        $testToken = Mockery::mock(AuthTokenContract::class);

        /**
         * @var TokenGuard
         */
        $guard = $this->app
            ->get('auth')
            ->guard(TokenType::ACCESS->getGuardName());
        $guard->setCurrentToken($testToken);

        $this->app->get('auth')->shouldUse(TokenType::ACCESS->getGuardName());

        $this->assertSame($testToken, $this->manager->currentToken());
    }

    public function testCurrentTokenReturnsNullIfUsedGuardIsNotAnTokenGuard(): void {
        $this->app->get('auth')->shouldUse('web');

        $this->assertNull($this->manager->currentToken());
    }

    /**
     * @dataProvider tokenTypeProvider
     */
    public function testActingAsWithUserAndSpecificTokenType(
        TokenType $type
    ): void {
        $user = $this->createMockUser();

        $this->assertNull(auth()->user());

        $token = $this->manager->actingAs($user, tokenType: $type);

        $this->assertEquals($user, auth()->user());
        $this->assertEquals($type, $token->getType());

        $this->assertAuthenticatedAs($user);

        $this->assertEquals($token, $this->manager->currentToken());
    }

    public function testActingAsWithNoAbilities(): void {
        $user = $this->createMockUser();
        $token = $this->manager->actingAs(
            $user,
            abilities: [],
            tokenType: TokenType::ACCESS
        );

        $this->assertFalse($token->hasAbility('foo'));
    }

    public function testActingAsWithAllAbilities(): void {
        $user = $this->createMockUser();
        $token = $this->manager->actingAs($user, abilities: ['*']);

        $this->assertTrue($token->hasAbility('foo'));
        $this->assertTrue($token->hasAbility('*'));
    }

    public function testActingAsWithAbilities(): void {
        $user = $this->createMockUser();
        $token = $this->manager->actingAs($user, ['foo']);

        $this->assertTrue($token->hasAbility('foo'));
        $this->assertFalse($token->hasAbility('bar'));
    }

    public function testActingAsWithNoUser(): void {
        $user = $this->createMockUser();

        auth()
            ->guard(TokenType::ACCESS->getGuardName())
            ->setUser($user);
        auth()->shouldUse(TokenType::ACCESS->getGuardName());

        $this->assertEquals($user, auth()->user());

        $this->manager->actingAs(null);

        $this->assertNull(auth()->user());
    }

    private function createMockUser(int $id = 1): TestUser|MockInterface {
        /**
         * @var TestUser|MockInterface
         */
        $user = Mockery::mock(TestUser::class);

        $user->shouldReceive('getAuthIdentifier')->andReturn($id);

        return $user;
    }
}

class TokenGuardTestClass extends TokenGuard {
}

abstract class TestUser implements Authenticatable {
    use HasAuthTokens;
}
