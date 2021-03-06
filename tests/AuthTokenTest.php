<?php

namespace TokenAuth\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Orchestra\Testbench\TestCase;
use TokenAuth\Models\AuthToken;
use TokenAuth\Tests\Helpers\Traits\CanCreateToken;
use TokenAuth\Tests\Helpers\Traits\CanCreateUser;
use TokenAuth\Tests\Helpers\Traits\SetsUpDatabase;
use TokenAuth\TokenAuth;

/**
 * @covers \TokenAuth\Models\AuthToken
 * @uses \TokenAuth\NewAuthToken
 * @uses \TokenAuth\TokenAuthServiceProvider
 */
class AuthTokenTest extends TestCase {
    use SetsUpDatabase, RefreshDatabase, CanCreateToken, CanCreateUser;

    public function testDeleteAllTokensFromSameGroup() {
        $groupId = 1;
        $amountTokens = 15;

        $token = $this->createToken(groupId: $groupId);

        for ($i = 0; $i < $amountTokens; $i++) {
            $this->createToken(
                type: Arr::random([
                    TokenAuth::TYPE_ACCESS,
                    TokenAuth::TYPE_REFRESH,
                ]),
                groupId: $groupId
            );
        }

        $this->assertEquals(
            $amountTokens + 1, // the $token + the created tokens
            AuthToken::where('group_id', $groupId)->count()
        );

        $token->token->deleteAllTokensFromSameGroup();

        $this->assertEquals(0, AuthToken::where('group_id', $groupId)->count());
    }

    public function testDeleteAllTokensFromSameGroupNoGroupId() {
        $amountTokens = 15;

        $token = $this->createToken(userId: 1);

        for ($i = 0; $i < $amountTokens; $i++) {
            $this->createToken(
                type: Arr::random([
                    TokenAuth::TYPE_ACCESS,
                    TokenAuth::TYPE_REFRESH,
                ]),
                userId: 1
            );
        }

        $this->assertEquals(
            $amountTokens + 1, // the $token + the created tokens
            AuthToken::where('tokenable_id', 1)->count()
        );

        $token->token->deleteAllTokensFromSameGroup();

        $this->assertEquals(
            $amountTokens,
            AuthToken::where('tokenable_id', 1)->count()
        );
        $this->assertFalse(AuthToken::where('id', $token->token->id)->exists());
    }

    public function testRevokeToken() {
        $token = new AuthToken();

        $this->assertNull($token->revoked_at);

        $token->revoke();

        $this->assertNotNull($token->revoked_at);

        $this->assertTrue($token->isDirty('revoked_at'));
    }

    public function testTokenAbilities() {
        $token = new AuthToken();

        $token->abilities = [];

        $this->assertCant($token, '*');
        $this->assertCant($token, 'foo');

        $token->abilities = ['foo'];

        $this->assertCant($token, '*');
        $this->assertCan($token, 'foo');

        $token->abilities = ['foo', '*'];

        $this->assertCan($token, 'foo');
        $this->assertCan($token, 'bar');
        $this->assertCan($token, '*');
    }

    public function testGetType() {
        $token = new AuthToken([
            'type' => TokenAuth::TYPE_ACCESS,
        ]);

        $this->assertEquals(TokenAuth::TYPE_ACCESS, $token->getType());
    }

    public function testFindAccessToken() {
        $accessToken = $this->createToken(TokenAuth::TYPE_ACCESS);
        $refreshToken = $this->createToken(TokenAuth::TYPE_REFRESH);

        $foundAccessToken = AuthToken::findAccessToken(
            $accessToken->plainTextToken
        );

        $this->assertNotNull($foundAccessToken);
        $this->assertEquals($accessToken->token->id, $foundAccessToken->id);

        $this->assertNull(
            AuthToken::findAccessToken($refreshToken->plainTextToken)
        );
    }

    public function testFindRefreshToken() {
        $refreshToken = $this->createToken(TokenAuth::TYPE_REFRESH);
        $accessToken = $this->createToken(TokenAuth::TYPE_ACCESS);

        $foundRefreshToken = AuthToken::findRefreshToken(
            $refreshToken->plainTextToken
        );

        $this->assertNotNull($foundRefreshToken);
        $this->assertEquals($refreshToken->token->id, $foundRefreshToken->id);

        $this->assertNull(
            AuthToken::findRefreshToken($accessToken->plainTextToken)
        );
    }

    public function testIsRevoked() {
        $token = $this->createToken(TokenAuth::TYPE_ACCESS, save: false);

        $this->assertFalse($token->token->isRevoked());

        $token->token->revoke();

        $this->assertTrue($token->token->isRevoked());
    }

    public function testGetTokenableParent() {
        $user = $this->createUser();

        $token = $this->createToken(TokenAuth::TYPE_ACCESS, userId: $user->id);

        $this->assertNotNull($token->token->tokenable);
        $this->assertEquals($user->id, $token->token->tokenable->id);
        $this->assertEquals($user->name, $token->token->tokenable->name);
        $this->assertEquals($user->email, $token->token->tokenable->email);
    }

    private function assertCan(AuthToken $token, $ability) {
        $this->assertTrue($token->can($ability));
        $this->assertFalse($token->cant($ability));
    }

    private function assertCant(AuthToken $token, $ability) {
        $this->assertFalse($token->can($ability));
        $this->assertTrue($token->cant($ability));
    }
}
