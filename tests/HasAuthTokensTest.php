<?php

namespace TokenAuth\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use TokenAuth\Tests\Helpers\Traits\CanCreateToken;
use TokenAuth\Tests\Helpers\Traits\CanCreateUser;
use TokenAuth\Tests\Helpers\Traits\SetsUpDatabase;
use TokenAuth\TokenAuth;

/**
 * @covers \TokenAuth\Traits\HasAuthTokens
 * @uses \TokenAuth\Models\AuthToken::can
 * @uses \TokenAuth\NewAuthToken
 * @uses \TokenAuth\TokenAuthServiceProvider
 */
class HasAuthTokensTest extends TestCase {
    use SetsUpDatabase, RefreshDatabase, CanCreateUser, CanCreateToken;

    public function testCreateSimpleToken() {
        $user = $this->createUser();

        $token = $user->createToken(TokenAuth::TYPE_ACCESS, 'AccessToken');

        $this->assertFalse($token->token->isDirty());
        $this->assertEquals(TokenAuth::TYPE_ACCESS, $token->token->type);
        $this->assertEquals(
            $token->token->created_at
                ->addMinutes(
                    config('tokenAuth.token_expiration_minutes.access')
                )
                ->valueOf(),
            $token->token->expires_at->valueOf()
        );
    }

    public function testCreateTokenWithoutSaving() {
        $user = $this->createUser();

        $token = $user->createToken(
            TokenAuth::TYPE_REFRESH,
            'RefreshToken',
            save: false
        );

        $this->assertTrue($token->token->isDirty());
        $this->assertEquals(TokenAuth::TYPE_REFRESH, $token->token->type);
    }

    public function testCreateTokenWithExpiration() {
        $user = $this->createUser();

        $expiresInMinutes = 10;

        $token = $user->createToken(
            TokenAuth::TYPE_ACCESS,
            'AccessToken',
            expiresInMinutes: $expiresInMinutes
        );

        $this->assertEquals(
            $token->token->created_at->addMinutes($expiresInMinutes)->valueOf(),
            $token->token->expires_at->valueOf()
        );
    }

    public function testCreateTokenThatNeverExpires() {
        $user = $this->createUser();

        $token = $user->createToken(
            TokenAuth::TYPE_ACCESS,
            'AccessToken',
            expiresInMinutes: -1,
            save: false
        );

        $this->assertNull($token->token->expires_at);
    }

    public function testGetTokens() {
        $user = $this->createUser();

        $this->assertEquals(0, $user->tokens()->count());

        $user->createToken(TokenAuth::TYPE_ACCESS, 'AccessToken');

        $this->assertEquals(1, $user->tokens()->count());
    }

    public function testWithToken() {
        $user = $this->createUser();
        $token = $user->createToken(
            TokenAuth::TYPE_ACCESS,
            'AccessToken',
            save: false
        );

        $this->assertNull($user->currentToken());

        $user->withToken($token->token);

        $this->assertEquals($token->token, $user->currentToken());
    }

    public function testTokenCan() {
        $user = $this->createUser();
        $token = $user->createToken(
            TokenAuth::TYPE_ACCESS,
            'AccessToken',
            abilities: ['foo'],
            save: false
        );
        $user->withToken($token->token);

        $this->assertTrue($user->tokenCan('foo'));
        $this->assertFalse($user->tokenCan('bar'));
    }

    public function testTokensInCurrentGroup() {
        $user = $this->createUser();

        $this->assertNull($user->tokensInCurrentGroup());

        $amountTokens = 15;
        $groupId = 1;

        $token = $this->createToken(groupId: $groupId, userId: $user->id);

        $user->withToken($token->token);

        $this->assertEquals(1, $user->tokensInCurrentGroup()->count());

        for ($i = 0; $i < $amountTokens; $i++) {
            $this->createToken(groupId: $groupId, userId: $user->id);
        }

        $this->createToken(groupId: $groupId + 1, userId: $user->id); // should not be contained in the result

        $this->assertEquals(
            $amountTokens + 1, // the $token + the created tokens
            $user->tokensInCurrentGroup()->count()
        );
    }
}
