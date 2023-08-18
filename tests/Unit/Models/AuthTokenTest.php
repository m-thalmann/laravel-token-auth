<?php

namespace TokenAuth\Tests\Unit\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use TokenAuth\Enums\TokenType;
use TokenAuth\Models\AuthToken;
use TokenAuth\Support\AuthTokenBuilder;
use TokenAuth\Tests\Helpers\HasTokenTypeProvider;
use TokenAuth\Tests\Helpers\Models\UserTestModel;
use TokenAuth\Tests\Helpers\UsesDatabase;

/**
 * @covers \TokenAuth\Models\AuthToken
 *
 * @uses \TokenAuth\Support\AuthTokenBuilder
 * @uses \TokenAuth\Support\NewAuthToken
 */
class AuthTokenTest extends TestCase {
    use HasTokenTypeProvider, UsesDatabase;

    /**
     * @dataProvider tokenTypeProvider
     */
    public function testScopeTypeReturnsTokensWithTheGivenType(
        TokenType $tokenType
    ): void {
        $this->createUsersTable();
        $user = $this->createTestUser();

        $token = AuthToken::create($tokenType)
            ->setAuthenticatable($user)
            ->build();

        $tokenWithOtherType = AuthToken::create(
            $tokenType === TokenType::ACCESS
                ? TokenType::REFRESH
                : TokenType::ACCESS
        )
            ->setAuthenticatable($user)
            ->build();

        $foundTokens = AuthToken::query()
            ->type($tokenType)
            ->get();

        $this->assertCount(1, $foundTokens);
        $this->assertEquals($token->token->id, $foundTokens->first()->id);
    }

    /**
     * @dataProvider tokenTypeProvider
     */
    public function testGetTypeReturnsTheType(TokenType $tokenType): void {
        $token = new AuthToken();
        $token->type = $tokenType;

        $this->assertEquals($tokenType, $token->getType());
    }

    public function testGetAuthenticatableReturnsTheAuthenticatable(): void {
        $testUser = Mockery::mock(Authenticatable::class);

        $token = new AuthToken();
        $token->authenticatable()->associate($testUser);

        $this->assertSame($testUser, $token->getAuthenticatable());
    }

    public function testGetGroupIdReturnsTheGroupId(): void {
        $testGroupId = 123;

        $token = new AuthToken();
        $token->group_id = $testGroupId;

        $this->assertEquals($testGroupId, $token->getGroupId());
    }

    public function testGetNameReturnsTheName(): void {
        $testName = 'test';

        $token = new AuthToken();
        $token->name = $testName;

        $this->assertEquals($testName, $token->getName());
    }

    public function testGetAbilitiesReturnsTheAbilities(): void {
        $testAbilities = ['test'];

        $token = new AuthToken();
        $token->abilities = $testAbilities;

        $this->assertEquals($testAbilities, $token->getAbilities());
    }

    public function testGetRevokedAtReturnsTheRevokedAt(): void {
        $testRevokedAt = now()->subHour();

        $token = new AuthToken();
        $token->revoked_at = $testRevokedAt;

        $this->assertLessThanOrEqual(
            1,
            $testRevokedAt->diffInSeconds($token->getRevokedAt())
        );
    }

    public function testGetExpiresAtReturnsTheExpiresAt(): void {
        $testExpiresAt = now()->addWeek();

        $token = new AuthToken();
        $token->expires_at = $testExpiresAt;

        $this->assertLessThanOrEqual(
            1,
            $testExpiresAt->diffInSeconds($token->getExpiresAt())
        );
    }

    public function testSetTokenHashesTheTokenAndSetsIt(): void {
        $testToken = 'test';
        $hashedToken = 'my_token_hash';

        /**
         * @var AuthTokenTestClass|MockInterface
         */
        $token = Mockery::mock(AuthTokenTestClass::class);
        $token->makePartial();

        $token
            ->shouldReceive('hashToken')
            ->with($testToken)
            ->once()
            ->andReturn($hashedToken);

        $token->setToken($testToken);

        $this->assertEquals($hashedToken, $token->token);
    }

    public function testStoreSavesTheModel(): void {
        /**
         * @var AuthToken|MockInterface
         */
        $token = Mockery::mock(AuthToken::class);
        $token->makePartial();

        $token->shouldReceive('save')->once();

        $token->store();
    }

    public function testRemoveDeletesTheModel(): void {
        /**
         * @var AuthToken|MockInterface
         */
        $token = Mockery::mock(AuthToken::class);
        $token->makePartial();

        $token->shouldReceive('delete')->once();

        $token->remove();
    }

    public function testRevokeSetsTheRevokedAtToNowWithoutSaving(): void {
        /**
         * @var AuthToken|MockInterface
         */
        $token = Mockery::mock(AuthToken::class);
        $token->makePartial();

        $token->shouldNotReceive('save');

        $this->assertNull($token->revoked_at);

        $token->revoke();

        $this->assertLessThanOrEqual(
            1,
            now()->diffInSeconds($token->revoked_at)
        );
    }

    public function testPrunableReturnsABuilderThatFetchesAllTokensToDelete(): void {
        $typeRevokedHours = [];

        foreach (TokenType::cases() as $index => $tokenType) {
            $time = $index + 1;

            $typeRevokedHours[$tokenType->value] = $time;

            config([
                "tokenAuth.prune_revoked_after_hours.{$tokenType->value}" => $time,
            ]);
        }

        $this->createUsersTable();
        $user = $this->createTestUser();

        $expiredTokens = collect(TokenType::cases())->map(
            fn(TokenType $type) => AuthToken::create($type)
                ->setAuthenticatable($user)
                ->setExpiresAt(now())
                ->build()
        );

        $notExpiredTokens = collect(TokenType::cases())->map(
            fn(TokenType $type) => AuthToken::create($type)
                ->setAuthenticatable($user)
                ->setExpiresAt(now()->addMinute())
                ->build()
        );

        $revokedTokens = collect(TokenType::cases())->map(function (
            TokenType $type
        ) use ($user, $typeRevokedHours) {
            $token = AuthToken::create($type)
                ->setAuthenticatable($user)
                ->build(save: false);

            $token->token->revoked_at = now()->subHours(
                $typeRevokedHours[$type->value]
            );

            $token->token->store();

            return $token;
        });

        $revokedButNotPrunableTokens = collect(TokenType::cases())->map(
            function (TokenType $type) use ($user, $typeRevokedHours) {
                $token = AuthToken::create($type)
                    ->setAuthenticatable($user)
                    ->build(save: false);

                $token->token->revoked_at = now()->subHours(
                    $typeRevokedHours[$type->value] - 1
                );

                $token->token->store();

                return $token;
            }
        );

        $prunableTokens = (new AuthToken())->prunable()->get();
        $prunableTokensIds = $prunableTokens->pluck('id');

        $tokensToDelete = $expiredTokens->merge($revokedTokens);

        $this->assertCount($tokensToDelete->count(), $prunableTokens);

        foreach ($tokensToDelete as $token) {
            $this->assertContains($token->token->id, $prunableTokensIds);
        }
    }

    /**
     * @dataProvider tokenTypeProvider
     */
    public function testFindReturnsTokenWithMatchingTypeAndToken(
        TokenType $tokenType
    ): void {
        $plainTextToken = 'test-token';

        $this->createUsersTable();
        $user = $this->createTestUser();

        $token = AuthToken::create($tokenType)
            ->setToken($plainTextToken)
            ->setAuthenticatable($user)
            ->build();

        $foundToken = AuthToken::find($tokenType, $plainTextToken);

        $this->assertNotNull($foundToken);
        $this->assertEquals($token->token->id, $foundToken->id);
    }

    public function testFindReturnsTokenIfExpectedTypeIsNull(): void {
        $plainTextToken = 'test-token';

        $this->createUsersTable();
        $user = $this->createTestUser();

        $token = AuthToken::create(TokenType::CUSTOM)
            ->setToken($plainTextToken)
            ->setAuthenticatable($user)
            ->build();

        $foundToken = AuthToken::find(null, $plainTextToken);

        $this->assertNotNull($foundToken);
        $this->assertEquals($token->token->id, $foundToken->id);
    }

    public function testFindReturnsNullIfTokenIsNotFound(): void {
        $foundToken = AuthToken::find(TokenType::ACCESS, 'test-token');

        $this->assertNull($foundToken);
    }

    public function testFindReturnsNullIfTypeDoesNotMatch(): void {
        $plainTextToken = 'test-token';

        $this->createUsersTable();
        $user = $this->createTestUser();

        $token = AuthToken::create(TokenType::REFRESH)
            ->setToken($plainTextToken)
            ->setAuthenticatable($user)
            ->build();

        $foundToken = AuthToken::find(TokenType::ACCESS, $plainTextToken);

        $this->assertNull($foundToken);
    }

    public function testFindReturnsNullIfTokenIsNotActive(): void {
        $plainTextToken = 'test-token';

        $this->createUsersTable();
        $user = $this->createTestUser();

        $token = AuthToken::create(TokenType::ACCESS)
            ->setToken($plainTextToken)
            ->setAuthenticatable($user)
            ->setExpiresAt(now()->subHour())
            ->build();

        $foundToken = AuthToken::find(TokenType::ACCESS, $plainTextToken);

        $this->assertNull($foundToken);
    }

    public function testFindReturnsTokenIfTokenDoesNotNeedToBeActive(): void {
        $plainTextToken = 'test-token';

        $this->createUsersTable();
        $user = $this->createTestUser();

        $token = AuthToken::create(TokenType::ACCESS)
            ->setToken($plainTextToken)
            ->setAuthenticatable($user)
            ->setExpiresAt(now()->subHour())
            ->build();

        $foundToken = AuthToken::find(
            TokenType::ACCESS,
            $plainTextToken,
            mustBeActive: false
        );

        $this->assertNotNull($foundToken);
        $this->assertEquals($token->token->id, $foundToken->id);
    }

    /**
     * @dataProvider tokenTypeProvider
     */
    public function testCreateReturnsAnAuthTokenBuilderWithTheSetType(
        TokenType $tokenType
    ): void {
        $builder = AuthTokenTestClass::create($tokenType);

        $token = $builder->build(save: false);

        $this->assertInstanceOf(AuthTokenBuilder::class, $builder);
        $this->assertInstanceOf(AuthTokenTestClass::class, $token->token);
        $this->assertEquals($tokenType, $token->token->getType());
    }

    public function testGenerateGroupIdReturnsTheNextAvailableGroupIdForTheAuthenticatable(): void {
        $this->createUsersTable();
        $user = $this->createTestUser();

        $token1 = AuthToken::create(TokenType::ACCESS)
            ->setAuthenticatable($user)
            ->setGroupId(1)
            ->build();

        $token2 = AuthToken::create(TokenType::ACCESS)
            ->setAuthenticatable($user)
            ->setGroupId(2)
            ->build();

        $this->assertEquals(3, AuthToken::generateGroupId($user));
    }

    public function testGenerateGroupIdReturns1IfNoTokenExistsForTheAuthenticatable(): void {
        $this->createUsersTable();
        $user = $this->createTestUser();

        $this->assertEquals(1, AuthToken::generateGroupId($user));
    }

    public function testDeleteTokensFromGroupDeletesAllTokensWithTheGivenGroupId(): void {
        $testGroupId = 1;

        $this->createUsersTable();
        $user = $this->createTestUser();

        $tokensGroup = collect(TokenType::cases())->map(
            fn(TokenType $type) => AuthToken::create($type)
                ->setAuthenticatable($user)
                ->setGroupId($testGroupId)
                ->build()
        );

        $tokensOtherGroup = collect(TokenType::cases())->map(
            fn(TokenType $type) => AuthToken::create($type)
                ->setAuthenticatable($user)
                ->setGroupId($testGroupId + 1)
                ->build()
        );

        AuthToken::deleteTokensFromGroup($testGroupId);

        foreach ($tokensGroup as $token) {
            $this->assertDatabaseMissing('auth_tokens', [
                'id' => $token->token->id,
            ]);
        }

        foreach ($tokensOtherGroup as $token) {
            $this->assertDatabaseHas('auth_tokens', [
                'id' => $token->token->id,
            ]);
        }
    }

    /**
     * @dataProvider tokenTypeProvider
     */
    public function testDeleteTokensFromGroupDeletesAllTokensWithTheGivenGroupIdAndType(
        TokenType $tokenType
    ): void {
        $testGroupId = 1;

        $this->createUsersTable();
        $user = $this->createTestUser();

        $tokens = collect(TokenType::cases())->map(
            fn(TokenType $type) => AuthToken::create($type)
                ->setAuthenticatable($user)
                ->setGroupId($testGroupId)
                ->build()
        );

        AuthToken::deleteTokensFromGroup($testGroupId, $tokenType);

        $this->assertDatabaseMissing('auth_tokens', [
            'group_id' => $testGroupId,
            'type' => $tokenType,
        ]);

        foreach (TokenType::cases() as $type) {
            if ($type === $tokenType) {
                continue;
            }

            $this->assertDatabaseHas('auth_tokens', [
                'group_id' => $testGroupId,
                'type' => $type,
            ]);
        }
    }

    private function createTestUser() {
        return UserTestModel::forceCreate([
            'email' => 'test@example.com',
            'password' =>
                '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ]);
    }
}

class AuthTokenTestClass extends AuthToken {
    public static function hashToken(string $plainTextToken): string {
        return parent::hashToken($plainTextToken);
    }
}
