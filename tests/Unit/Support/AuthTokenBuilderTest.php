<?php

namespace TokenAuth\Tests\Unit\Support;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use TokenAuth\Enums\TokenType;
use TokenAuth\Models\AuthToken;
use TokenAuth\Support\AuthTokenBuilder;
use TokenAuth\Support\NewAuthToken;
use TokenAuth\Tests\Helpers\HasTokenTypeProvider;

/**
 * @covers \TokenAuth\Support\AuthTokenBuilder
 *
 * @uses \TokenAuth\Support\NewAuthToken
 */
class AuthTokenBuilderTest extends TestCase {
    use HasTokenTypeProvider;

    /**
     * @dataProvider tokenTypeProvider
     */
    public function testSetTypeSetsTheTypeOnTheModel(
        TokenType $tokenType
    ): void {
        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass
            ->shouldReceive('setAttribute')
            ->with('type', $tokenType)
            ->once();

        $builder = new AuthTokenBuilderTestClass($testClass);

        $this->assertSame($builder, $builder->setType($tokenType));
    }

    public function testSetAuthenticatableSetsTheAuthenticatableOnTheModel(): void {
        $testUser = Mockery::mock(Authenticatable::class);

        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass
            ->shouldReceive('authenticatable->associate')
            ->with($testUser)
            ->once();

        $builder = new AuthTokenBuilderTestClass($testClass);

        $this->assertSame($builder, $builder->setAuthenticatable($testUser));
    }

    public function testSetGroupIdSetsTheGroupIdOnTheModel(): void {
        $testGroupId = 444;

        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass
            ->shouldReceive('setAttribute')
            ->with('group_id', $testGroupId)
            ->once();

        $builder = new AuthTokenBuilderTestClass($testClass);

        $this->assertSame($builder, $builder->setGroupId($testGroupId));
    }

    public function testSetNameSetsTheNameOnTheModel(): void {
        $testName = 'test name';

        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass
            ->shouldReceive('setAttribute')
            ->with('name', $testName)
            ->once();

        $builder = new AuthTokenBuilderTestClass($testClass);

        $this->assertSame($builder, $builder->setName($testName));
    }

    public function testSetTokenSetsTheTokenOnTheModel(): void {
        $testToken = 'test token';

        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass
            ->shouldReceive('setToken')
            ->with($testToken)
            ->once();

        $builder = new AuthTokenBuilderTestClass($testClass);

        $this->assertSame($builder, $builder->setToken($testToken));
    }

    public function testSetAbilitiesSetsTheAbilitiesOnTheModel(): void {
        $testAbilities = ['test', 'abilities'];

        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass
            ->shouldReceive('setAttribute')
            ->with('abilities', $testAbilities)
            ->once();

        $builder = new AuthTokenBuilderTestClass($testClass);

        $this->assertSame($builder, $builder->setAbilities(...$testAbilities));
    }

    public function testAddAbilitiesMergesTheAbilitiesAndSetsThemOnTheModel(): void {
        $testAbilities = ['test', 'abilities'];
        $testAbilitiesToAdd = ['more', 'abilities'];

        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass
            ->shouldReceive('getAttribute')
            ->with('abilities')
            ->andReturn($testAbilities);

        $testClass
            ->shouldReceive('setAttribute')
            ->with(
                'abilities',
                array_merge($testAbilities, $testAbilitiesToAdd)
            )
            ->once();

        $builder = new AuthTokenBuilderTestClass($testClass);

        $this->assertSame(
            $builder,
            $builder->addAbilities(...$testAbilitiesToAdd)
        );
    }

    public function testGetAbilitiesReturnsTheSetAbilitiesOnTheModel(): void {
        $testAbilities = ['test', 'abilities'];

        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass
            ->shouldReceive('getAttribute')
            ->with('abilities')
            ->andReturn($testAbilities);

        $builder = new AuthTokenBuilderTestClass($testClass);

        $this->assertSame($testAbilities, $builder->getAbilities());
    }

    public function testSetExpiresAtSetsTheExpiresAtOnTheModel(): void {
        $testExpiresAt = now()->addMinutes(5);

        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass
            ->shouldReceive('setAttribute')
            ->with('expires_at', $testExpiresAt)
            ->once();

        $builder = new AuthTokenBuilderTestClass($testClass);

        $this->assertSame($builder, $builder->setExpiresAt($testExpiresAt));
    }

    /**
     * @dataProvider tokenTypeProvider
     */
    public function testUseConfiguredExpirationSetsTheExpiresAtFromTheConfigOnTheModel(
        TokenType $tokenType
    ): void {
        $testExpirationMinutes = 5;

        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass
            ->shouldReceive('getType')
            ->once()
            ->andReturn($tokenType);

        $testClass
            ->shouldReceive('setAttribute')
            ->withArgs(
                fn(
                    string $attribute,
                    CarbonInterface $expiresAt
                ) => $attribute === 'expires_at' &&
                    $expiresAt->diffInMinutes(
                        now()->addMinutes($testExpirationMinutes)
                    ) === 0
            )
            ->once();

        $builder = new AuthTokenBuilderTestClass($testClass);

        config([
            "tokenAuth.expiration_minutes.{$tokenType->value}" => $testExpirationMinutes,
        ]);

        $builder->useConfiguredExpiration();
    }

    /**
     * @dataProvider tokenTypeProvider
     */
    public function testUseConfiguredExpirationDoesNotSetTheExpiresAtIfTheConfigValueIsNull(
        TokenType $tokenType
    ): void {
        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass
            ->shouldReceive('getType')
            ->once()
            ->andReturn($tokenType);

        $testClass->shouldNotReceive('setAttribute');

        $builder = new AuthTokenBuilderTestClass($testClass);

        config(["tokenAuth.expiration_minutes.{$tokenType->value}" => null]);

        $builder->useConfiguredExpiration();
    }

    public function testBuildSavesTheModelAndReturnsANewAuthTokenInstance(): void {
        $testToken = 'test token';
        $testExpiresAt = now()->addMinutes(5);

        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass->shouldReceive('getType')->andReturn(TokenType::ACCESS);

        $testClass
            ->shouldReceive('store')
            ->withNoArgs()
            ->once();

        $testClass
            ->shouldReceive('setToken')
            ->with($testToken)
            ->once();

        $testClass
            ->shouldReceive('setAttribute')
            ->with('expires_at', $testExpiresAt)
            ->once();

        $testClass->shouldIgnoreMissing();

        $builder = new AuthTokenBuilderTestClass($testClass);

        $builder->setToken($testToken);
        $builder->setExpiresAt($testExpiresAt);

        $newAuthToken = $builder->build();

        $this->assertInstanceOf(NewAuthToken::class, $newAuthToken);
        $this->assertSame($testClass, $newAuthToken->token);
        $this->assertSame($testToken, $newAuthToken->plainTextToken);
    }

    public function testBuildGeneratesRandomTokenIfNoTokenSet(): void {
        $testToken = null;

        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass->shouldReceive('getType')->andReturn(TokenType::ACCESS);

        $testClass
            ->shouldReceive('setToken')
            ->withArgs(function (string $token) use (&$testToken) {
                $testToken = $token;
                return true;
            })
            ->once();
        $testClass->shouldIgnoreMissing();

        $builder = new AuthTokenBuilderTestClass($testClass);

        $newAuthToken = $builder->build();

        $this->assertSame($testToken, $newAuthToken->plainTextToken);
    }

    public function testBuildSetsExpiresAtToConfiguredExpirationIfNoExpirationWasSet(): void {
        $testExpirationMinutes = 100;

        config([
            'tokenAuth.expiration_minutes.' .
            TokenType::ACCESS->value => $testExpirationMinutes,
        ]);

        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass->shouldReceive('getType')->andReturn(TokenType::ACCESS);

        $testClass
            ->shouldReceive('setAttribute')
            ->withArgs(
                fn(
                    string $attribute,
                    CarbonInterface $expiresAt
                ) => $attribute === 'expires_at' &&
                    $expiresAt->diffInMinutes(
                        now()->addMinutes($testExpirationMinutes)
                    ) === 0
            )
            ->once();

        $testClass->shouldIgnoreMissing();

        $builder = new AuthTokenBuilderTestClass($testClass);

        $builder->build();
    }

    public function testBuildDoesNotSaveTheInstanceIfDisabled(): void {
        /**
         * @var AuthToken|MockInterface
         */
        $testClass = Mockery::mock(AuthToken::class);

        $testClass->shouldReceive('getType')->andReturn(TokenType::ACCESS);

        $testClass->shouldNotReceive('store');
        $testClass->shouldIgnoreMissing();

        $builder = new AuthTokenBuilderTestClass($testClass);

        $builder->build(save: false);
    }
}

class AuthTokenBuilderTestClass extends AuthTokenBuilder {
    public function useConfiguredExpiration(): void {
        parent::useConfiguredExpiration();
    }
}
