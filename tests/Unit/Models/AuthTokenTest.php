<?php

namespace TokenAuth\Tests\Unit\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

#[CoversClass(AuthToken::class)]
#[UsesClass(TokenType::class)]
#[UsesClass(TokenAuth::class)]
#[UsesClass(AuthTokenBuilder::class)]
#[UsesClass(NewAuthToken::class)]
#[UsesClass(TokenAuthManager::class)]
#[UsesClass(TokenAuthServiceProvider::class)]
class AuthTokenTest extends TestCase {
    use LazilyRefreshDatabase;

    #[DataProvider('tokenTypeProvider')]
    public function testScopeTypeReturnsTokensWithTheGivenType(
        TokenType $tokenType
    ): void {
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

    #[DataProvider('tokenTypeProvider')]
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

    #[DataProvider('tokenTypeProvider')]
    public function testFindReturnsTokenWithMatchingTypeAndToken(
        TokenType $tokenType
    ): void {
        $plainTextToken = 'test-token';

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

    #[DataProvider('tokenTypeProvider')]
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
        $user = $this->createTestUser();

        $this->assertEquals(1, AuthToken::generateGroupId($user));
    }

    public function testDeleteTokensFromGroupDeletesAllTokensWithTheGivenGroupId(): void {
        $testGroupId = 1;

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

    #[DataProvider('tokenTypeProvider')]
    public function testDeleteTokensFromGroupDeletesAllTokensWithTheGivenGroupIdAndType(
        TokenType $tokenType
    ): void {
        $testGroupId = 1;

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
}

class AuthTokenTestClass extends AuthToken {
}
