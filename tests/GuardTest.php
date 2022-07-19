<?php

namespace TokenAuth\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Orchestra\Testbench\TestCase;
use TokenAuth\Guard;
use TokenAuth\Tests\Helpers\Traits\SetsUpDatabase;
use TokenAuth\TokenAuth;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use TokenAuth\Events\RevokedTokenReused;
use TokenAuth\Events\TokenAuthenticated;
use TokenAuth\Models\AuthToken;
use TokenAuth\Tests\Helpers\Models\TestUserNoTokens;
use TokenAuth\Tests\Helpers\Traits\CanCreateToken;
use TokenAuth\Tests\Helpers\Traits\CanCreateUser;

/**
 * @covers \TokenAuth\Guard
 * @covers \TokenAuth\Events\RevokedTokenReused
 * @covers \TokenAuth\Events\TokenAuthenticated
 * @uses \TokenAuth\Traits\HasAuthTokens
 * @uses \TokenAuth\Models\AuthToken
 * @uses \TokenAuth\TokenAuthServiceProvider
 * @uses \TokenAuth\NewAuthToken
 * @uses \TokenAuth\TokenAuth
 */
class GuardTest extends TestCase {
    use SetsUpDatabase, RefreshDatabase, CanCreateUser, CanCreateToken;

    public function testAuthenticateWithNoToken() {
        $guards = $this->createGuards();

        $request = Request::create('/', 'GET');

        foreach ($guards as $guard) {
            $user = $guard->__invoke($request);
            $this->assertNull($user);
        }
    }

    public function testAuthenticateWithNonExistentToken() {
        $guards = $this->createGuards();

        $request = $this->createRequest('test_token');

        foreach ($guards as $guard) {
            $user = $guard->__invoke($request);
            $this->assertNull($user);
        }
    }

    public function testAuthenticateWithExpiredToken() {
        $guards = $this->createGuards();

        foreach ($guards as $guard) {
            $user = $this->createUser();

            $token = $this->createToken(
                $guard->getTokenType(),
                userId: $user->id,
                save: false
            );

            $tokenInstance = $token->token;

            $tokenInstance->forceFill(['expires_at' => now()])->save();

            $request = $this->createRequest($token->plainTextToken);

            $guardUser = $guard->__invoke($request);
            $this->assertNull($guardUser);
        }
    }

    public function testAuthenticateWithRevokedToken() {
        $guards = $this->createGuards();

        foreach ($guards as $guard) {
            $user = $this->createUser();

            $token = $this->createToken(
                $guard->getTokenType(),
                userId: $user->id,
                save: false
            );

            $tokenInstance = $token->token;

            $tokenInstance->forceFill(['revoked_at' => now()])->save();

            $request = $this->createRequest($token->plainTextToken);

            $guardUser = Event::fakeFor(function () use (
                $guard,
                $request,
                $tokenInstance
            ) {
                $guardUser = $guard->__invoke($request);

                Event::assertDispatched(function (
                    RevokedTokenReused $event
                ) use ($tokenInstance) {
                    return $tokenInstance->id === $event->token->id;
                });

                return $guardUser;
            });

            $this->assertNull($guardUser);
            $this->assertFalse(
                AuthToken::where('id', $tokenInstance->id)->exists()
            );
        }
    }

    public function testAuthenticateWithWrongTokenType() {
        $guards = $this->createGuards();

        $user = $this->createUser();

        foreach ($guards as $guard) {
            $type =
                $guard->getTokenType() === TokenAuth::TYPE_ACCESS
                    ? TokenAuth::TYPE_REFRESH
                    : TokenAuth::TYPE_ACCESS;

            $token = $this->createToken($type, userId: $user->id);

            $request = $this->createRequest($token->plainTextToken);

            $guardUser = $guard->__invoke($request);
            $this->assertNull($guardUser);
        }
    }

    public function testAuthenticateWithTokenableThatDoesntSupportTokens() {
        $guards = $this->createGuards();

        $user = TestUserNoTokens::create([
            'email' => 'john@doe.com',
            'name' => 'John Doe',
            'password' =>
                '$2a$12$CV9PJXeDrEcLHlC0kVlQcemiQ/CFt5jgVEXtaMfjPonJXFMQgFqui',
        ]);

        foreach ($guards as $guard) {
            $plainTextToken = Str::random(32);

            AuthToken::forceCreate([
                'tokenable_id' => $user->id,
                'tokenable_type' => TestUserNoTokens::class,
                'type' => $guard->getTokenType(),
                'name' => 'TestName',
                'token' => hash('sha256', $plainTextToken),
                'abilities' => ['*'],
            ]);

            $request = $this->createRequest($plainTextToken);

            $guardUser = $guard->__invoke($request);

            $this->assertNull($guardUser);
        }
    }

    public function testAuthenticateWithValidToken() {
        $guards = $this->createGuards();

        foreach ($guards as $guard) {
            $user = $this->createUser();
            $token = $this->createToken(
                $guard->getTokenType(),
                userId: $user->id
            );

            $tokenInstance = $token->token;

            $this->assertNull($tokenInstance->last_used_at);

            $request = $this->createRequest($token->plainTextToken);

            $guardUser = Event::fakeFor(function () use (
                $tokenInstance,
                $guard,
                $request
            ) {
                $guardUser = $guard->__invoke($request);

                Event::assertDispatched(function (
                    TokenAuthenticated $event
                ) use ($tokenInstance) {
                    return $tokenInstance->id === $event->token->id;
                });

                return $guardUser;
            });

            $this->assertNotNull($guardUser);
            $this->assertEquals($user->id, $guardUser->id);

            $tokenInstance->refresh();

            $this->assertNotNull($tokenInstance->last_used_at);
            $this->assertEqualsWithDelta(
                now()->timestamp,
                $tokenInstance->last_used_at->timestamp,
                1
            );
        }
    }

    public function testAuthenticateGuardWithInvalidType() {
        $factory = Mockery::mock(AuthFactory::class);

        $guard = $this->createGuard($factory, 'invalid_type');

        $request = $this->createRequest('test_token');

        $this->assertThrows(function () use ($guard, $request) {
            $guard->__invoke($request);
        });
    }

    public function testAuthenticateWithCustomHeader() {
        $guards = $this->createGuards();

        $user = $this->createUser();

        $headerName = 'X-Custom-Token-Header';

        TokenAuth::getAuthTokenFromRequestUsing(
            fn($request) => $request->header($headerName)
        );

        foreach ($guards as $guard) {
            $token = $this->createToken(
                $guard->getTokenType(),
                userId: $user->id
            );

            $request = Request::create('/', 'GET');
            $request->headers->set($headerName, $token->plainTextToken);

            $guardUser = $guard->__invoke($request);
            $this->assertNotNull($guardUser);
            $this->assertEquals($user->id, $guardUser->id);
        }

        // reset for next tests
        TokenAuth::$authTokenRetrievalCallback = null;
    }

    public function testAuthenticateWithCustomHeaderAndTokenInAuthorizationHeader() {
        $guards = $this->createGuards();

        $user = $this->createUser();

        $headerName = 'X-Custom-Token-Header';

        TokenAuth::getAuthTokenFromRequestUsing(
            fn($request) => $request->header($headerName)
        );

        foreach ($guards as $guard) {
            $token = $this->createToken(
                $guard->getTokenType(),
                userId: $user->id
            );

            $request = $this->createRequest($token->plainTextToken);

            $guardUser = $guard->__invoke($request);
            $this->assertNull($guardUser);
        }

        // reset for next tests
        TokenAuth::$authTokenRetrievalCallback = null;
    }

    public function testAuthenticateExpiredTokenWithAuthenticationCallbackAlwaysTrue() {
        $guards = $this->createGuards();

        $user = $this->createUser();

        TokenAuth::authenticateAuthTokensUsing(function ($token, $isValid) {
            return true;
        });

        foreach ($guards as $guard) {
            $token = $this->createToken(
                $guard->getTokenType(),
                userId: $user->id,
                save: false
            );

            $tokenInstance = $token->token;

            $tokenInstance->forceFill(['expires_at' => now()])->save();

            $request = $this->createRequest($token->plainTextToken);

            $guardUser = $guard->__invoke($request);
            $this->assertNotNull($guardUser);
            $this->assertEquals($user->id, $guardUser->id);
        }

        // reset for next tests
        TokenAuth::$authTokenAuthenticationCallback = null;
    }

    public function testAuthenticateWithAuthenticationCallbackAlwaysFalse() {
        $guards = $this->createGuards();

        $user = $this->createUser();

        TokenAuth::authenticateAuthTokensUsing(function ($token, $isValid) {
            return false;
        });

        foreach ($guards as $guard) {
            $token = $this->createToken(
                $guard->getTokenType(),
                userId: $user->id
            );

            $request = $this->createRequest($token->plainTextToken);

            $guardUser = $guard->__invoke($request);
            $this->assertNull($guardUser);
        }

        // reset for next tests
        TokenAuth::$authTokenAuthenticationCallback = null;
    }

    private function createGuards() {
        $factory = Mockery::mock(AuthFactory::class);

        $tokenGuard = $this->createGuard($factory, TokenAuth::TYPE_ACCESS);
        $tokenRefreshGuard = $this->createGuard(
            $factory,
            TokenAuth::TYPE_REFRESH
        );

        return [$tokenGuard, $tokenRefreshGuard];
    }

    private function createGuard($authFactory, $type) {
        $guard = new Guard($authFactory, $type);
        $authFactory
            ->shouldReceive('guard')
            ->with('token')
            ->andReturn($guard);

        return $guard;
    }

    private function createRequest($token) {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        return $request;
    }
}
