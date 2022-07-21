<?php

namespace TokenAuth\Tests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use TokenAuth\Contracts\HasAbilities;
use TokenAuth\Exceptions\MissingAbilityException;
use TokenAuth\Tests\Helpers\Traits\CanCreateUser;
use TokenAuth\Tests\Helpers\Traits\SetsUpDatabase;
use TokenAuth\TokenAuth;
use TokenAuth\Traits\CanCreateTokens;

/**
 * @covers \TokenAuth\Traits\CanCreateTokens
 * @uses \TokenAuth\Traits\HasAuthTokens
 * @uses \TokenAuth\Models\AuthToken
 * @uses \TokenAuth\Exceptions\MissingAbilityException
 * @uses \TokenAuth\NewAuthToken
 * @uses \TokenAuth\TokenAuth
 * @uses \TokenAuth\TokenAuthServiceProvider
 */
class CanCreateTokensTest extends TestCase {
    use SetsUpDatabase, RefreshDatabase, CanCreateUser;

    private const REFRESH_TOKEN_NAME = 'TestRefreshToken';
    private const ACCESS_TOKEN_NAME = 'TestAccessToken';

    private const REFRESH_TOKEN_EXPIRATION = 60;
    private const ACCESS_TOKEN_EXPIRATION = 10;

    /**
     * @uses \TokenAuth\TokenAuthGuard
     */
    public function testCreateTokenPairForAuthUserIsSameAsForUser() {
        $user = $this->createUser();

        $this->setAuthUser($user);

        $refreshAbilities = ['refresh', 'admin'];
        $accessAbilities = ['admin'];

        [$refreshToken1, $accessToken1] = CreateTokensClass::createTokenPair(
            self::REFRESH_TOKEN_NAME,
            self::ACCESS_TOKEN_NAME,
            [$refreshAbilities, $accessAbilities],
            [self::REFRESH_TOKEN_EXPIRATION, self::ACCESS_TOKEN_EXPIRATION]
        );

        [
            $refreshToken2,
            $accessToken2,
        ] = CreateTokensClass::createTokenPairForUser(
            $user,
            self::REFRESH_TOKEN_NAME,
            self::ACCESS_TOKEN_NAME,
            [$refreshAbilities, $accessAbilities],
            [self::REFRESH_TOKEN_EXPIRATION, self::ACCESS_TOKEN_EXPIRATION]
        );

        foreach (
            [[$refreshToken1, $refreshToken2], [$accessToken1, $accessToken2]]
            as [$token1, $token2]
        ) {
            $this->assertEquals(
                $token2->token->tokenable_id,
                $token1->token->tokenable_id
            );
            $this->assertEquals($token2->token->type, $token1->token->type);
            $this->assertEquals($token2->token->name, $token1->token->name);
            $this->assertEquals(
                $token2->token->abilities,
                $token1->token->abilities
            );
            $this->assertEqualsWithDelta(
                $token2->token->expires_at->timestamp,
                $token1->token->expires_at->timestamp,
                1
            );
        }
    }

    public function testCreateTokenPairUnauthenticated() {
        $this->expectException(AuthorizationException::class);

        CreateTokensClass::createTokenPair('Test1', 'Test2');
    }

    public function testCreateTokenPairForUser() {
        $user = $this->createUser();

        $refreshAbilities = ['*'];
        $accessAbilities = ['admin'];

        [
            $refreshToken,
            $accessToken,
        ] = CreateTokensClass::createTokenPairForUser(
            $user,
            self::REFRESH_TOKEN_NAME,
            self::ACCESS_TOKEN_NAME,
            [$refreshAbilities, $accessAbilities],
            [self::REFRESH_TOKEN_EXPIRATION, self::ACCESS_TOKEN_EXPIRATION]
        );

        $this->assertEquals(
            TokenAuth::TYPE_REFRESH,
            $refreshToken->token->type
        );
        $this->assertEquals(TokenAuth::TYPE_ACCESS, $accessToken->token->type);

        $this->assertEquals($user->id, $refreshToken->token->tokenable_id);
        $this->assertEquals($user->id, $accessToken->token->tokenable_id);

        $this->assertEquals(
            self::REFRESH_TOKEN_NAME,
            $refreshToken->token->name
        );
        $this->assertEquals(self::ACCESS_TOKEN_NAME, $accessToken->token->name);

        $this->assertEquals($refreshAbilities, $refreshToken->token->abilities);
        $this->assertEquals($accessAbilities, $accessToken->token->abilities);

        $this->assertEqualsWithDelta(
            $refreshToken->token->created_at->addMinutes(
                self::REFRESH_TOKEN_EXPIRATION
            )->timestamp,
            $refreshToken->token->expires_at->timestamp,
            1
        );
        $this->assertEqualsWithDelta(
            $accessToken->token->created_at->addMinutes(
                self::ACCESS_TOKEN_EXPIRATION
            )->timestamp,
            $accessToken->token->expires_at->timestamp,
            1
        );

        $this->assertEquals(
            $refreshToken->token->group_id,
            $accessToken->token->group_id
        );

        $this->assertTrue(
            $user
                ->tokens()
                ->where('id', $refreshToken->token->id)
                ->exists()
        );
        $this->assertTrue(
            $user
                ->tokens()
                ->where('id', $accessToken->token->id)
                ->exists()
        );
    }

    public function testCreateTokenPairForUserNoSave() {
        $user = $this->createUser();

        [
            $refreshToken,
            $accessToken,
        ] = CreateTokensClass::createTokenPairForUser(
            $user,
            self::REFRESH_TOKEN_NAME,
            self::ACCESS_TOKEN_NAME,
            save: false
        );

        $this->assertFalse(
            $user
                ->tokens()
                ->where('id', $refreshToken->token->id)
                ->exists()
        );
        $this->assertFalse(
            $user
                ->tokens()
                ->where('id', $accessToken->token->id)
                ->exists()
        );
    }

    public function testCreateTokenPairForUserWhereAccessTokenAbilitiesAreNotSubsetRefreshTokenAbilities() {
        $user = $this->createUser();

        $this->assertThrows(function () use ($user) {
            CreateTokensClass::createTokenPairForUser(
                $user,
                self::REFRESH_TOKEN_NAME,
                self::ACCESS_TOKEN_NAME,
                [['refresh', 'view-users'], ['view-users', 'admin']]
            );
        }, MissingAbilityException::class);
    }

    /**
     * @uses \TokenAuth\TokenAuthGuard
     */
    public function testRotateRefreshTokenForAuthUserIsSameAsForUser() {
        $user = $this->createUser();

        $oldRefreshToken1 = $user->createToken(
            TokenAuth::TYPE_REFRESH,
            self::REFRESH_TOKEN_NAME,
            1,
            expiresInMinutes: self::REFRESH_TOKEN_EXPIRATION
        );
        $oldRefreshToken2 = $user->createToken(
            TokenAuth::TYPE_REFRESH,
            self::REFRESH_TOKEN_NAME,
            1,
            expiresInMinutes: self::REFRESH_TOKEN_EXPIRATION
        );

        $user->withToken($oldRefreshToken1->token);

        $this->setAuthUser($user, 'token-refresh');

        $accessAbilities = ['admin'];

        [$refreshToken1, $accessToken1] = CreateTokensClass::rotateRefreshToken(
            self::ACCESS_TOKEN_NAME,
            $accessAbilities,
            [self::REFRESH_TOKEN_EXPIRATION, self::ACCESS_TOKEN_EXPIRATION]
        );

        [
            $refreshToken2,
            $accessToken2,
        ] = CreateTokensClass::rotateRefreshTokenForUser(
            $user,
            $oldRefreshToken2->token,
            self::ACCESS_TOKEN_NAME,
            $accessAbilities,
            [self::REFRESH_TOKEN_EXPIRATION, self::ACCESS_TOKEN_EXPIRATION]
        );

        foreach (
            [[$refreshToken1, $refreshToken2], [$accessToken1, $accessToken2]]
            as [$token1, $token2]
        ) {
            $this->assertEquals(
                $token2->token->tokenable_id,
                $token1->token->tokenable_id
            );
            $this->assertEquals($token2->token->type, $token1->token->type);
            $this->assertEquals($token2->token->name, $token1->token->name);
            $this->assertEquals(
                $token2->token->abilities,
                $token1->token->abilities
            );
            $this->assertEqualsWithDelta(
                $token2->token->expires_at->timestamp,
                $token1->token->expires_at->timestamp,
                1
            );
        }
    }

    public function testRotateRefreshTokenUnauthenticated() {
        $this->expectException(AuthorizationException::class);

        CreateTokensClass::rotateRefreshToken(self::ACCESS_TOKEN_NAME);
    }

    /**
     * @uses \TokenAuth\TokenAuthGuard
     */
    public function testRotateRefreshTokenForAuthUserNoToken() {
        $this->setAuthUser($this->createUser());

        $this->assertThrows(function () {
            CreateTokensClass::rotateRefreshToken(self::ACCESS_TOKEN_NAME);
        }, AuthorizationException::class);
    }

    public function testRotateRefreshTokenForUser() {
        $user = $this->createUser();

        $refreshAbilities = ['*'];
        $accessAbilities = ['admin'];

        $oldRefreshToken = $user->createToken(
            TokenAuth::TYPE_REFRESH,
            'OldName',
            1,
            $refreshAbilities
        );

        $this->assertFalse($oldRefreshToken->token->isRevoked());

        [
            $refreshToken,
            $accessToken,
        ] = CreateTokensClass::rotateRefreshTokenForUser(
            $user,
            $oldRefreshToken->token,
            self::ACCESS_TOKEN_NAME,
            $accessAbilities,
            [self::REFRESH_TOKEN_EXPIRATION, self::ACCESS_TOKEN_EXPIRATION]
        );

        $this->assertTrue($oldRefreshToken->token->isRevoked());

        $this->assertEquals(
            TokenAuth::TYPE_REFRESH,
            $refreshToken->token->type
        );
        $this->assertEquals(TokenAuth::TYPE_ACCESS, $accessToken->token->type);

        $this->assertEquals($user->id, $refreshToken->token->tokenable_id);
        $this->assertEquals($user->id, $accessToken->token->tokenable_id);

        $this->assertEquals(
            $oldRefreshToken->token->name,
            $refreshToken->token->name
        );
        $this->assertEquals(self::ACCESS_TOKEN_NAME, $accessToken->token->name);

        $this->assertEquals($refreshAbilities, $refreshToken->token->abilities);
        $this->assertEquals($accessAbilities, $accessToken->token->abilities);

        $this->assertEqualsWithDelta(
            $refreshToken->token->created_at->addMinutes(
                self::REFRESH_TOKEN_EXPIRATION
            )->timestamp,
            $refreshToken->token->expires_at->timestamp,
            1
        );
        $this->assertEqualsWithDelta(
            $accessToken->token->created_at->addMinutes(
                self::ACCESS_TOKEN_EXPIRATION
            )->timestamp,
            $accessToken->token->expires_at->timestamp,
            1
        );

        $this->assertEquals(
            $oldRefreshToken->token->group_id,
            $refreshToken->token->group_id
        );
        $this->assertEquals(
            $refreshToken->token->group_id,
            $accessToken->token->group_id
        );

        $this->assertTrue(
            $user
                ->tokens()
                ->where('id', $refreshToken->token->id)
                ->exists()
        );
        $this->assertTrue(
            $user
                ->tokens()
                ->where('id', $accessToken->token->id)
                ->exists()
        );
    }

    public function testRotateRefreshTokenForUserNoSave() {
        $user = $this->createUser();

        $refreshAbilities = ['*'];
        $accessAbilities = ['admin'];

        $oldRefreshToken = $user->createToken(
            TokenAuth::TYPE_REFRESH,
            'OldName',
            1,
            $refreshAbilities
        );

        $this->assertFalse($oldRefreshToken->token->isRevoked());

        [
            $refreshToken,
            $accessToken,
        ] = CreateTokensClass::rotateRefreshTokenForUser(
            $user,
            $oldRefreshToken->token,
            self::ACCESS_TOKEN_NAME,
            $accessAbilities,
            [self::REFRESH_TOKEN_EXPIRATION, self::ACCESS_TOKEN_EXPIRATION],
            false
        );

        $this->assertTrue($oldRefreshToken->token->isRevoked());

        $this->assertFalse(
            $user
                ->tokens()
                ->where('id', $refreshToken->token->id)
                ->exists()
        );
        $this->assertFalse(
            $user
                ->tokens()
                ->where('id', $accessToken->token->id)
                ->exists()
        );

        $this->assertTrue(
            $user
                ->tokens()
                ->where('id', $oldRefreshToken->token->id)
                ->whereNull('revoked_at')
                ->exists()
        );
    }

    public function testRotateRefreshTokenForUserWithAccessToken() {
        $user = $this->createUser();

        $accessToken = $user->createToken(
            TokenAuth::TYPE_ACCESS,
            self::ACCESS_TOKEN_NAME
        );

        $this->assertThrows(function () use ($user, $accessToken) {
            CreateTokensClass::rotateRefreshTokenForUser(
                $user,
                $accessToken->token,
                self::ACCESS_TOKEN_NAME
            );
        }, InvalidArgumentException::class);
    }

    /**
     * @uses \TokenAuth\TokenAuthGuard
     */
    public function testCreateAccessTokenForAuthUserIsSameAsForUser() {
        $user = $this->createUser();

        $this->setAuthUser($user);

        $abilities = ['admin'];

        $accessToken1 = CreateTokensClass::createAccessToken(
            self::ACCESS_TOKEN_NAME,
            $abilities,
            self::ACCESS_TOKEN_EXPIRATION
        );

        $accessToken2 = CreateTokensClass::createAccessTokenForUser(
            $user,
            self::ACCESS_TOKEN_NAME,
            $abilities,
            self::ACCESS_TOKEN_EXPIRATION
        );

        $this->assertEquals(
            $accessToken2->token->tokenable_id,
            $accessToken1->token->tokenable_id
        );
        $this->assertEquals(
            $accessToken2->token->type,
            $accessToken1->token->type
        );
        $this->assertEquals(
            $accessToken2->token->name,
            $accessToken1->token->name
        );
        $this->assertEquals(
            $accessToken2->token->abilities,
            $accessToken1->token->abilities
        );
        $this->assertEqualsWithDelta(
            $accessToken2->token->expires_at->timestamp,
            $accessToken1->token->expires_at->timestamp,
            1
        );
    }

    public function testCreateAccessTokenUnauthenticated() {
        $this->expectException(AuthorizationException::class);

        CreateTokensClass::createAccessToken('Test1');
    }

    public function testCreateAccessTokenForUser() {
        $user = $this->createUser();

        $abilities = ['admin'];

        $accessToken = CreateTokensClass::createAccessTokenForUser(
            $user,
            self::ACCESS_TOKEN_NAME,
            $abilities,
            self::ACCESS_TOKEN_EXPIRATION
        );

        $this->assertEquals(TokenAuth::TYPE_ACCESS, $accessToken->token->type);

        $this->assertEquals($user->id, $accessToken->token->tokenable_id);

        $this->assertEquals(self::ACCESS_TOKEN_NAME, $accessToken->token->name);

        $this->assertEquals($abilities, $accessToken->token->abilities);

        $this->assertEqualsWithDelta(
            $accessToken->token->created_at->addMinutes(
                self::ACCESS_TOKEN_EXPIRATION
            )->timestamp,
            $accessToken->token->expires_at->timestamp,
            1
        );

        $this->assertNull($accessToken->token->group_id);

        $this->assertTrue(
            $user
                ->tokens()
                ->where('id', $accessToken->token->id)
                ->exists()
        );
    }

    public function testCreateAccessTokenForUserNoSave() {
        $user = $this->createUser();

        $accessToken = CreateTokensClass::createAccessTokenForUser(
            $user,
            self::ACCESS_TOKEN_NAME,
            save: false
        );

        $this->assertFalse(
            $user
                ->tokens()
                ->where('id', $accessToken->token->id)
                ->exists()
        );
    }

    public function testGetNextGroupId() {
        $user = $this->createUser();

        for ($i = 0; $i < 5; $i++) {
            // when no token with the group is inserted, it stays at 1
            $this->assertEquals(
                1,
                CreateTokensClass::getNextTokenGroupId($user->id)
            );
        }

        $user->createToken(
            TokenAuth::TYPE_ACCESS,
            'Token',
            CreateTokensClass::getNextTokenGroupId($user->id)
        );

        $this->assertEquals(
            2,
            CreateTokensClass::getNextTokenGroupId($user->id)
        );
    }

    public function testGetNextGroupIdWithDifferentUsers() {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        $this->assertEquals(
            1,
            CreateTokensClass::getNextTokenGroupId($user1->id)
        );
        $this->assertEquals(
            1,
            CreateTokensClass::getNextTokenGroupId($user2->id)
        );

        $user1->createToken(
            TokenAuth::TYPE_ACCESS,
            'Token',
            CreateTokensClass::getNextTokenGroupId($user1->id)
        );

        $this->assertEquals(
            2,
            CreateTokensClass::getNextTokenGroupId($user1->id)
        );
        $this->assertEquals(
            1,
            CreateTokensClass::getNextTokenGroupId($user2->id)
        );
    }

    public function testCheckHasAbilitiesWithWildcard() {
        CreateTokensClass::checkHasAllAbilities(new HasAbilitiesClass(['*']), [
            ['*'],
        ]);
        CreateTokensClass::checkHasAllAbilities(new HasAbilitiesClass(['*']), [
            ['foo'],
        ]);

        $this->addToAssertionCount(1);
    }

    public function testCheckHasAbilitiesIsSubset() {
        CreateTokensClass::checkHasAllAbilities(
            new HasAbilitiesClass(['foo', 'bar']),
            ['foo']
        );
        CreateTokensClass::checkHasAllAbilities(
            new HasAbilitiesClass(['foo', 'bar']),
            ['foo', 'bar']
        );

        $this->addToAssertionCount(1);
    }

    public function testCheckHasAbilitiesIsNotSubset() {
        $this->assertThrows(function () {
            CreateTokensClass::checkHasAllAbilities(
                new HasAbilitiesClass(['foo', 'bar']),
                ['baz']
            );
        }, MissingAbilityException::class);

        $this->assertThrows(function () {
            CreateTokensClass::checkHasAllAbilities(
                new HasAbilitiesClass(['foo']),
                ['foo', 'bar', 'baz']
            );
        }, MissingAbilityException::class);
    }

    private function setAuthUser($user, $guard = 'token') {
        auth()
            ->guard($guard)
            ->setUser($user);
        auth()->shouldUse($guard);
    }
}

class CreateTokensClass {
    use CanCreateTokens {
        checkHasAllAbilities as parentCheckHasAllAbilities;
    }

    public static function checkHasAllAbilities(
        HasAbilities $abilitiesObject,
        array $checkAbilities
    ) {
        return self::parentCheckHasAllAbilities(
            $abilitiesObject,
            $checkAbilities
        );
    }
}

class HasAbilitiesClass implements HasAbilities {
    private array $abilities;

    public function __construct(array $abilities) {
        $this->abilities = $abilities;
    }

    public function can($ability) {
        return in_array('*', $this->abilities) ||
            in_array($ability, $this->abilities);
    }

    public function cant($ability) {
        return !$this->can($ability);
    }
}
