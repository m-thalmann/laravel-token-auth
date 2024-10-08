<?php

namespace TokenAuth\Tests\Unit\Support;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use TokenAuth\Enums\TokenType;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Models\AuthToken;
use TokenAuth\Support\AuthTokenBuilder;
use TokenAuth\Support\NewAuthToken;
use TokenAuth\Tests\TestCase;
use TokenAuth\TokenAuthManager;
use TokenAuth\TokenAuthServiceProvider;

#[CoversClass(AuthTokenBuilder::class)]
#[CoversClass(NewAuthToken::class)]
#[UsesClass(TokenType::class)]
#[UsesClass(TokenAuth::class)]
#[UsesClass(TokenAuthManager::class)]
#[UsesClass(TokenAuthServiceProvider::class)]
class AuthTokenBuilderTest extends TestCase {
    private AuthToken|MockInterface $testTokenInstance;
    private AuthTokenBuilderTestClass $builder;

    protected function setUp(): void {
        parent::setUp();

        /** @var AuthToken|MockInterface  */
        $this->testTokenInstance = Mockery::mock(AuthToken::class);
        $this->builder = new AuthTokenBuilderTestClass(
            $this->testTokenInstance
        );
    }

    #[DataProvider('tokenTypeProvider')]
    public function testSetTypeSetsTheTypeOnTheModel(
        TokenType $tokenType
    ): void {
        $this->testTokenInstance
            ->shouldReceive('setAttribute')
            ->with('type', $tokenType)
            ->once();

        $this->assertSame($this->builder, $this->builder->setType($tokenType));
    }

    public function testSetAuthenticatableSetsTheAuthenticatableOnTheModel(): void {
        /** @var Authenticatable|MockInterface */
        $testUser = Mockery::mock(Authenticatable::class);

        $this->testTokenInstance
            ->shouldReceive('authenticatable->associate')
            ->with($testUser)
            ->once();

        $this->assertSame(
            $this->builder,
            $this->builder->setAuthenticatable($testUser)
        );
    }

    public function testSetGroupIdSetsTheGroupIdOnTheModel(): void {
        $testGroupId = 444;

        $this->testTokenInstance
            ->shouldReceive('setAttribute')
            ->with('group_id', $testGroupId)
            ->once();

        $this->assertSame(
            $this->builder,
            $this->builder->setGroupId($testGroupId)
        );
    }

    public function testSetNameSetsTheNameOnTheModel(): void {
        $testName = 'test name';

        $this->testTokenInstance
            ->shouldReceive('setAttribute')
            ->with('name', $testName)
            ->once();

        $this->assertSame($this->builder, $this->builder->setName($testName));
    }

    public function testSetTokenSetsTheTokenOnTheModel(): void {
        $testToken = 'test';
        $hashedToken = 'my_token_hash';

        $this->testTokenInstance
            ->shouldReceive('hashToken')
            ->with($testToken)
            ->once()
            ->andReturn($hashedToken);

        $this->testTokenInstance
            ->shouldReceive('setAttribute')
            ->with('token', $hashedToken)
            ->once();

        $this->assertSame($this->builder, $this->builder->setToken($testToken));
    }

    public function testSetAbilitiesSetsTheAbilitiesOnTheModel(): void {
        $testAbilities = ['test', 'abilities'];

        $this->testTokenInstance
            ->shouldReceive('setAttribute')
            ->with('abilities', $testAbilities)
            ->once();

        $this->assertSame(
            $this->builder,
            $this->builder->setAbilities(...$testAbilities)
        );
    }

    public function testAddAbilitiesMergesTheAbilitiesAndSetsThemOnTheModel(): void {
        $testAbilities = ['test', 'abilities'];
        $testAbilitiesToAdd = ['more', 'abilities'];

        $this->testTokenInstance
            ->shouldReceive('getAttribute')
            ->with('abilities')
            ->andReturn($testAbilities);

        $this->testTokenInstance
            ->shouldReceive('setAttribute')
            ->with(
                'abilities',
                array_merge($testAbilities, $testAbilitiesToAdd)
            )
            ->once();

        $this->assertSame(
            $this->builder,
            $this->builder->addAbilities(...$testAbilitiesToAdd)
        );
    }

    public function testGetAbilitiesReturnsTheSetAbilitiesOnTheModel(): void {
        $testAbilities = ['test', 'abilities'];

        $this->testTokenInstance
            ->shouldReceive('getAttribute')
            ->with('abilities')
            ->andReturn($testAbilities);

        $this->assertSame($testAbilities, $this->builder->getAbilities());
    }

    public function testSetExpiresAtSetsTheExpiresAtOnTheModel(): void {
        $testExpiresAt = now()->addMinutes(5);

        $this->testTokenInstance
            ->shouldReceive('setAttribute')
            ->with('expires_at', $testExpiresAt)
            ->once();

        $this->assertSame(
            $this->builder,
            $this->builder->setExpiresAt($testExpiresAt)
        );
    }

    #[DataProvider('tokenTypeProvider')]
    public function testUseConfiguredExpirationSetsTheExpiresAtFromTheConfigOnTheModel(
        TokenType $tokenType
    ): void {
        $testExpirationMinutes = 5;

        $this->testTokenInstance
            ->shouldReceive('getType')
            ->once()
            ->andReturn($tokenType);

        $this->testTokenInstance
            ->shouldReceive('setAttribute')
            ->withArgs(function (string $attribute, mixed $expiresAt) use (
                $testExpirationMinutes
            ) {
                if ($attribute !== 'expires_at') {
                    return false;
                }

                $this->assertEqualsWithDelta(
                    0,
                    $expiresAt->diffInMinutes(
                        now()->addMinutes($testExpirationMinutes)
                    ),
                    0.01
                );

                return true;
            })
            ->once();

        config([
            "tokenAuth.expiration_minutes.{$tokenType->value}" => $testExpirationMinutes,
        ]);

        $this->builder->useConfiguredExpiration();
    }

    #[DataProvider('tokenTypeProvider')]
    public function testUseConfiguredExpirationSetsTheExpiresAtToNullIfNotSetInConfig(
        TokenType $tokenType
    ): void {
        $this->testTokenInstance
            ->shouldReceive('getType')
            ->once()
            ->andReturn($tokenType);

        $this->testTokenInstance
            ->shouldReceive('setAttribute')
            ->withArgs(
                fn(string $attribute, mixed $expiresAt) => $attribute ===
                    'expires_at' && $expiresAt === null
            )
            ->once();

        config([
            "tokenAuth.expiration_minutes.{$tokenType->value}" => null,
        ]);

        $this->builder->useConfiguredExpiration();
    }

    public function testBuildSavesTheModelAndReturnsANewAuthTokenInstance(): void {
        $testToken = 'test token';
        $hashedToken = 'my_token_hash';
        $testExpiresAt = now()->addMinutes(5);

        $this->testTokenInstance
            ->shouldReceive('hashToken')
            ->with($testToken)
            ->once()
            ->andReturn($hashedToken);

        $this->testTokenInstance
            ->shouldReceive('getType')
            ->andReturn(TokenType::ACCESS);

        $this->testTokenInstance
            ->shouldReceive('store')
            ->withNoArgs()
            ->once();

        $this->testTokenInstance
            ->shouldReceive('setAttribute')
            ->with('token', $hashedToken)
            ->once();

        $this->testTokenInstance
            ->shouldReceive('setAttribute')
            ->with('expires_at', $testExpiresAt)
            ->once();

        $this->testTokenInstance->shouldIgnoreMissing();

        $this->builder->setToken($testToken);
        $this->builder->setExpiresAt($testExpiresAt);

        $newAuthToken = $this->builder->build();

        $this->assertInstanceOf(NewAuthToken::class, $newAuthToken);
        $this->assertSame($this->testTokenInstance, $newAuthToken->token);
        $this->assertSame($testToken, $newAuthToken->plainTextToken);
    }

    public function testBuildGeneratesRandomTokenIfNoTokenSet(): void {
        $testToken = null;
        $hashedToken = 'my_token_hash';

        $this->testTokenInstance
            ->shouldReceive('hashToken')
            ->withArgs(function (string $token) use (&$testToken) {
                $testToken = $token;
                return true;
            })
            ->once()
            ->andReturn($hashedToken);

        $this->testTokenInstance
            ->shouldReceive('getType')
            ->andReturn(TokenType::ACCESS);

        $this->testTokenInstance
            ->shouldReceive('setAttribute')
            ->with('token', $hashedToken)
            ->once();

        $this->testTokenInstance->shouldIgnoreMissing();

        $newAuthToken = $this->builder->build();

        $this->assertSame($testToken, $newAuthToken->plainTextToken);
    }

    public function testBuildSetsExpiresAtToConfiguredExpirationIfNoExpirationWasSet(): void {
        $testExpirationMinutes = 100;

        config([
            'tokenAuth.expiration_minutes.' .
            TokenType::ACCESS->value => $testExpirationMinutes,
        ]);

        $this->testTokenInstance
            ->shouldReceive('getType')
            ->andReturn(TokenType::ACCESS);

        $this->testTokenInstance
            ->shouldReceive('setAttribute')
            ->withArgs(function (string $attribute, mixed $expiresAt) use (
                $testExpirationMinutes
            ) {
                if ($attribute !== 'expires_at') {
                    return false;
                }

                $this->assertEqualsWithDelta(
                    0,
                    $expiresAt->diffInMinutes(
                        now()->addMinutes($testExpirationMinutes)
                    ),
                    0.01
                );

                return true;
            })
            ->once();

        $this->testTokenInstance->shouldIgnoreMissing();

        $this->builder->build();
    }

    public function testBuildDoesNotSaveTheInstanceIfDisabled(): void {
        $this->testTokenInstance
            ->shouldReceive('getType')
            ->andReturn(TokenType::ACCESS);

        $this->testTokenInstance->shouldNotReceive('store');
        $this->testTokenInstance->shouldIgnoreMissing();

        $this->builder->build(save: false);
    }
}

class AuthTokenBuilderTestClass extends AuthTokenBuilder {
    public function useConfiguredExpiration(): void {
        parent::useConfiguredExpiration();
    }
}
