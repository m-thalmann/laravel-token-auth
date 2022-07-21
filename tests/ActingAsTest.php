<?php

namespace TokenAuth\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use TokenAuth\Tests\Helpers\Traits\CanCreateUser;
use TokenAuth\Tests\Helpers\Traits\SetsUpDatabase;
use TokenAuth\TokenAuth;

/**
 * @covers \TokenAuth\TokenAuth::actingAs
 * @uses \TokenAuth\Guard
 * @uses \TokenAuth\TokenAuthServiceProvider
 * @uses \TokenAuth\Traits\HasAuthTokens
 */
class ActingAsTest extends TestCase {
    use SetsUpDatabase, RefreshDatabase, CanCreateUser;

    public function testActingAsUser() {
        $user = $this->createUser();

        $this->assertNull(auth()->user());

        TokenAuth::actingAs($user);

        $this->assertEquals($user, auth()->user());

        $token = $user->currentToken();

        $this->assertEquals(TokenAuth::TYPE_ACCESS, $token->getType());

        $this->assertAuthenticatedAs($user);
    }

    public function testActingAsUserWithRefreshToken() {
        $user = $this->createUser();

        $this->assertNull(auth()->user());

        TokenAuth::actingAs($user, guard: 'token-refresh');

        $this->assertEquals($user, auth()->user());

        $token = $user->currentToken();

        $this->assertEquals(TokenAuth::TYPE_REFRESH, $token->getType());
    }

    public function testActingAsWithNoAbilities() {
        $user = $this->createUser();
        TokenAuth::actingAs($user);
        $token = $user->currentToken();

        $this->assertFalse($token->can('foo'));
    }

    public function testActingAsWithAllAbilities() {
        $user = $this->createUser();
        TokenAuth::actingAs($user, ['*']);
        $token = $user->currentToken();

        $this->assertTrue($token->can('foo'));
        $this->assertTrue($token->can('*'));
    }

    public function testActingAsWithAbilities() {
        $user = $this->createUser();
        TokenAuth::actingAs($user, ['foo']);
        $token = $user->currentToken();

        $this->assertTrue($token->can('foo'));
        $this->assertFalse($token->can('bar'));
    }

    public function testActingAsWithNoUser() {
        $user = $this->createUser();

        auth()
            ->guard('token')
            ->setUser($user);
        auth()->shouldUse('token');

        $this->assertEquals($user, auth()->user());

        TokenAuth::actingAs(null);

        $this->assertNull(auth()->user());
    }
}
