<?php

namespace Tests\Unit\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Support\AuthTokenBuilder;
use TokenAuth\Support\AuthTokenPairBuilder;
use TokenAuth\Support\NewAuthToken;
use TokenAuth\Support\NewAuthTokenPair;
use TokenAuth\Tests\Helpers\UsesDatabase;

/**
 * @covers \TokenAuth\Support\AuthTokenPairBuilder
 *
 * @uses \TokenAuth\Support\NewAuthToken
 * @uses \TokenAuth\Support\NewAuthTokenPair
 */
class AuthTokenPairBuilderTest extends TestCase {
    use UsesDatabase;

    private AuthTokenBuilderContract|MockInterface $accessTokenBuilder;
    private AuthTokenBuilderContract|MockInterface $refreshTokenBuilder;
    private AuthTokenPairBuilderTestClass|MockInterface $builder;

    protected function setUp(): void {
        parent::setUp();

        /**
         * @var AuthTokenBuilderContract|MockInterface
         */
        $this->accessTokenBuilder = Mockery::mock(
            AuthTokenBuilderContract::class
        );
        /**
         * @var AuthTokenBuilderContract|MockInterface
         */
        $this->refreshTokenBuilder = Mockery::mock(
            AuthTokenBuilderContract::class
        );

        $this->accessTokenBuilder
            ->shouldReceive('getType')
            ->andReturn(TokenType::ACCESS);
        $this->refreshTokenBuilder
            ->shouldReceive('getType')
            ->andReturn(TokenType::REFRESH);

        /**
         * @var AuthTokenPairBuilderTestClass|MockInterface
         */
        $this->builder = Mockery::mock(AuthTokenPairBuilderTestClass::class, [
            $this->accessTokenBuilder,
            $this->refreshTokenBuilder,
        ]);
        $this->builder->makePartial();
    }

    public function testSetTypeThrowsALogicException(): void {
        $this->expectException(LogicException::class);
        $this->builder->setType(TokenType::ACCESS);
    }

    public function testSetAuthenticatableSetsAuthenticatableOnBothBuilders(): void {
        $authenticatable = Mockery::mock(Authenticatable::class);

        $this->accessTokenBuilder
            ->shouldReceive('setAuthenticatable')
            ->with($authenticatable)
            ->once();
        $this->refreshTokenBuilder
            ->shouldReceive('setAuthenticatable')
            ->with($authenticatable)
            ->once();

        $this->assertSame(
            $this->builder,
            $this->builder->setAuthenticatable($authenticatable)
        );
    }

    public function testSetGroupIdSetsGroupIdOnBothBuilders(): void {
        $groupId = 111;

        $this->accessTokenBuilder
            ->shouldReceive('setGroupId')
            ->with($groupId)
            ->once();
        $this->refreshTokenBuilder
            ->shouldReceive('setGroupId')
            ->with($groupId)
            ->once();

        $this->assertSame($this->builder, $this->builder->setGroupId($groupId));
    }

    public function testSetNameSetsNameOnBothBuilders(): void {
        $name = 'test';

        $this->accessTokenBuilder
            ->shouldReceive('setName')
            ->with($name)
            ->once();
        $this->refreshTokenBuilder
            ->shouldReceive('setName')
            ->with($name)
            ->once();

        $this->assertSame($this->builder, $this->builder->setName($name));
    }

    public function testSetTokenThrowsALogicException(): void {
        $this->expectException(LogicException::class);
        $this->builder->setToken('test');
    }

    public function testSetAbilitiesSetsAbilitiesOnBothBuilders(): void {
        $abilities = ['test1', 'test2'];

        $this->accessTokenBuilder
            ->shouldReceive('setAbilities')
            ->with(...$abilities)
            ->once();
        $this->refreshTokenBuilder
            ->shouldReceive('setAbilities')
            ->with(...$abilities)
            ->once();

        $this->assertSame(
            $this->builder,
            $this->builder->setAbilities(...$abilities)
        );
    }

    public function testAddAbilitiesAddsAbilitiesOnBothBuilders(): void {
        $abilities = ['test1', 'test2'];

        $this->accessTokenBuilder
            ->shouldReceive('addAbilities')
            ->with(...$abilities)
            ->once();
        $this->refreshTokenBuilder
            ->shouldReceive('addAbilities')
            ->with(...$abilities)
            ->once();

        $this->assertSame(
            $this->builder,
            $this->builder->addAbilities(...$abilities)
        );
    }

    public function testGetAbilitiesThrowsALogicException(): void {
        $this->expectException(LogicException::class);
        $this->builder->getAbilities();
    }

    public function testSetExpiresAtSetsExpiresAtOnBothBuilders(): void {
        $expiresAt = now();

        $this->accessTokenBuilder
            ->shouldReceive('setExpiresAt')
            ->with($expiresAt)
            ->once();
        $this->refreshTokenBuilder
            ->shouldReceive('setExpiresAt')
            ->with($expiresAt)
            ->once();

        $this->assertSame(
            $this->builder,
            $this->builder->setExpiresAt($expiresAt)
        );
    }

    public function testBuildThrowsALogicException(): void {
        $this->expectException(LogicException::class);
        $this->builder->build();
    }

    public function testBeforeBuildSaveAddsTheCallbackToTheQueue(): void {
        $callback1 = fn() => 'a';
        $callback2 = fn() => 'b';

        $this->assertCount(0, $this->builder->getBeforeBuildSaveCallbacks());

        $this->assertSame(
            $this->builder,
            $this->builder->beforeBuildSave($callback1)
        );

        $this->assertCount(1, $this->builder->getBeforeBuildSaveCallbacks());

        $this->assertSame(
            $callback1,
            $this->builder->getBeforeBuildSaveCallbacks()[0]
        );

        $this->builder->beforeBuildSave($callback2);

        $this->assertCount(2, $this->builder->getBeforeBuildSaveCallbacks());
    }

    public function testBuildPairBuildsTheTokensSavingThemAfterwardsAndReturningTheTokenPair(): void {
        /**
         * @var AuthTokenContract|MockInterface
         */
        $generatedAuthToken = Mockery::mock(AuthTokenContract::class);

        // 1 for each token
        $generatedAuthToken
            ->shouldReceive('store')
            ->withNoArgs()
            ->twice();

        $generatedNewAuthToken = new NewAuthToken(
            $generatedAuthToken,
            'test token'
        );

        $this->accessTokenBuilder
            ->shouldReceive('build')
            ->with(false)
            ->once()
            ->andReturn($generatedNewAuthToken);
        $this->refreshTokenBuilder
            ->shouldReceive('build')
            ->with(false)
            ->once()
            ->andReturn($generatedNewAuthToken);

        $this->builder
            ->shouldReceive('checkAbilitiesAreEqual')
            ->withNoArgs()
            ->once();

        $tokenPair = $this->builder->buildPair();

        $this->assertInstanceOf(NewAuthTokenPair::class, $tokenPair);
    }

    public function testBuildExecutesTheBeforeBuildSaveCallbacksBeforeSaving(): void {
        /**
         * @var mixed|MockInterface
         */
        $callback1 = Mockery::mock();
        /**
         * @var mixed|MockInterface
         */
        $callback2 = Mockery::mock();

        $callback1
            ->shouldReceive('call')
            ->withNoArgs()
            ->once()
            ->globally()
            ->ordered();
        $callback2
            ->shouldReceive('call')
            ->withNoArgs()
            ->once()
            ->globally()
            ->ordered();

        $this->builder->beforeBuildSave(fn() => $callback1->call());
        $this->builder->beforeBuildSave(fn() => $callback2->call());

        /**
         * @var AuthTokenContract|MockInterface
         */
        $generatedAuthToken = Mockery::mock(AuthTokenContract::class);

        $generatedAuthToken
            ->shouldReceive('store')
            ->withNoArgs()
            ->twice()
            ->globally()
            ->ordered();

        $generatedNewAuthToken = new NewAuthToken(
            $generatedAuthToken,
            'test token'
        );

        $this->accessTokenBuilder
            ->shouldReceive('build')
            ->with(false)
            ->once()
            ->andReturn($generatedNewAuthToken);
        $this->refreshTokenBuilder
            ->shouldReceive('build')
            ->with(false)
            ->once()
            ->andReturn($generatedNewAuthToken);

        $this->builder
            ->shouldReceive('checkAbilitiesAreEqual')
            ->withNoArgs()
            ->once();

        $this->builder->buildPair();
    }

    public function testCheckAbilitiesAreEqualReturnsVoidIfTheyMatch(): void {
        $abilities1 = ['test1', 'test2'];
        $abilities2 = ['test2', 'test1']; // just reordered

        $this->accessTokenBuilder
            ->shouldReceive('getAbilities')
            ->withNoArgs()
            ->once()
            ->andReturn($abilities1);
        $this->refreshTokenBuilder
            ->shouldReceive('getAbilities')
            ->withNoArgs()
            ->once()
            ->andReturn($abilities2);

        $this->assertNull($this->builder->checkAbilitiesAreEqual());
    }

    public function testCheckAbilitiesAreEqualThrowsAnExceptionIfTheyDontMatch(): void {
        $this->expectException(LogicException::class);

        $abilities1 = ['test1', 'test2'];
        $abilities2 = ['test3', 'test1']; // test3 is not in abilities1

        $this->accessTokenBuilder
            ->shouldReceive('getAbilities')
            ->withNoArgs()
            ->once()
            ->andReturn($abilities1);
        $this->refreshTokenBuilder
            ->shouldReceive('getAbilities')
            ->withNoArgs()
            ->once()
            ->andReturn($abilities2);

        $this->builder->checkAbilitiesAreEqual();
    }

    public function testFromTokenCreatesANewPairBuilderWithTheSamePropertiesAsTheGivenToken(): void {
        /**
         * @var AuthTokenContract|MockInterface
         */
        $token = Mockery::mock(AuthTokenContract::class);

        /**
         * @var Authenticatable|MockInterface
         */
        $testUser = Mockery::mock(Authenticatable::class);

        $testGroupId = 111;
        $testName = 'test name';
        $testAbilities = ['test1', 'test2'];

        $token
            ->shouldReceive('getAuthenticatable')
            ->withNoArgs()
            ->once()
            ->andReturn($testUser);
        $token
            ->shouldReceive('getGroupId')
            ->withNoArgs()
            ->once()
            ->andReturn($testGroupId);
        $token
            ->shouldReceive('getName')
            ->withNoArgs()
            ->once()
            ->andReturn($testName);
        $token
            ->shouldReceive('getAbilities')
            ->withNoArgs()
            ->once()
            ->andReturn($testAbilities);

        /**
         * @var AuthTokenBuilder|MockInterface
         */
        $tokenBuilder = Mockery::mock(AuthTokenBuilder::class);

        $tokenBuilder
            ->shouldReceive('setAuthenticatable')
            ->with($testUser)
            ->twice();
        $tokenBuilder
            ->shouldReceive('setGroupId')
            ->with($testGroupId)
            ->twice();
        $tokenBuilder
            ->shouldReceive('setName')
            ->with($testName)
            ->twice();
        $tokenBuilder
            ->shouldReceive('setAbilities')
            ->with(...$testAbilities)
            ->twice();

        $token
            ->shouldReceive('create')
            ->with(TokenType::ACCESS)
            ->once()
            ->andReturn($tokenBuilder);
        $token
            ->shouldReceive('create')
            ->with(TokenType::REFRESH)
            ->once()
            ->andReturn($tokenBuilder);

        $builder = AuthTokenPairBuilderTestClass::fromToken($token);

        $this->assertInstanceOf(AuthTokenPairBuilderTestClass::class, $builder);
    }
}

class AuthTokenPairBuilderTestClass extends AuthTokenPairBuilder {
    public function getBeforeBuildSaveCallbacks(): array {
        return $this->beforeBuildSaveCallbacks;
    }

    public function checkAbilitiesAreEqual(): void {
        parent::checkAbilitiesAreEqual();
    }
}
