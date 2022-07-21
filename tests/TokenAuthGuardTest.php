<?php

namespace TokenAuth\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use TokenAuth\TokenAuthGuard;
use TokenAuth\Tests\Helpers\Traits\SetsUpDatabase;
use TokenAuth\TokenAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use ReflectionClass;
use TokenAuth\Events\RevokedTokenReused;
use TokenAuth\Events\TokenAuthenticated;
use TokenAuth\Models\AuthToken;
use TokenAuth\Tests\Helpers\Models\TestUserNoTokens;
use TokenAuth\Tests\Helpers\Traits\CanCreateToken;
use TokenAuth\Tests\Helpers\Traits\CanCreateUser;

/**
 * @covers \TokenAuth\TokenAuthGuard
 * @covers \TokenAuth\Events\RevokedTokenReused
 * @covers \TokenAuth\Events\TokenAuthenticated
 * @uses \TokenAuth\Traits\HasAuthTokens
 * @uses \TokenAuth\Models\AuthToken
 * @uses \TokenAuth\TokenAuthServiceProvider
 * @uses \TokenAuth\NewAuthToken
 * @uses \TokenAuth\TokenAuth
 */
class TokenAuthGuardTest extends TestCase {
    use SetsUpDatabase, RefreshDatabase, CanCreateUser, CanCreateToken;

    public function testAuthenticateWithNoToken() {
        $request = Request::create('/', 'GET');

        foreach (TokenAuth::GUARDS_TOKEN_TYPES as $type) {
            $guard = $this->createGuard($type, $request);
            $user = $guard->user();
            $this->assertNull($user);
        }
    }

    public function testAuthenticateWithNonExistentToken() {
        $request = $this->createRequest('test_token');

        foreach (TokenAuth::GUARDS_TOKEN_TYPES as $type) {
            $guard = $this->createGuard($type, $request);
            $user = $guard->user();
            $this->assertNull($user);
        }
    }

    public function testAuthenticateWithExpiredToken() {
        foreach (TokenAuth::GUARDS_TOKEN_TYPES as $type) {
            $user = $this->createUser();

            $token = $this->createToken($type, userId: $user->id, save: false);

            $token->token->forceFill(['expires_at' => now()])->save();

            $request = $this->createRequest($token->plainTextToken);

            $guard = $this->createGuard($type, $request);
            $guardUser = $guard->user();
            $this->assertNull($guardUser);
        }
    }

    public function testAuthenticateWithRevokedToken() {
        foreach (TokenAuth::GUARDS_TOKEN_TYPES as $type) {
            $user = $this->createUser();

            $token = $this->createToken($type, userId: $user->id, save: false);

            $token->token->forceFill(['revoked_at' => now()])->save();

            $request = $this->createRequest($token->plainTextToken);

            $guard = $this->createGuard($type, $request);

            $guardUser = Event::fakeFor(function () use ($guard, $token) {
                $guardUser = $guard->user();

                Event::assertDispatched(function (
                    RevokedTokenReused $event
                ) use ($token) {
                    return $token->token->id === $event->token->id;
                });

                return $guardUser;
            });

            $this->assertNull($guardUser);
            $this->assertFalse(
                AuthToken::where('id', $token->token->id)->exists()
            );
        }
    }

    public function testAuthenticateWithWrongTokenType() {
        $user = $this->createUser();

        foreach (TokenAuth::GUARDS_TOKEN_TYPES as $type) {
            $newType =
                $type === TokenAuth::TYPE_ACCESS
                    ? TokenAuth::TYPE_REFRESH
                    : TokenAuth::TYPE_ACCESS;

            $token = $this->createToken($newType, userId: $user->id);

            $request = $this->createRequest($token->plainTextToken);
            $guard = $this->createGuard($type, $request);

            $guardUser = $guard->user();
            $this->assertNull($guardUser);
        }
    }

    public function testAuthenticateWithTokenableThatDoesntSupportTokens() {
        $user = TestUserNoTokens::create([
            'email' => 'john@doe.com',
            'name' => 'John Doe',
            'password' =>
                '$2a$12$CV9PJXeDrEcLHlC0kVlQcemiQ/CFt5jgVEXtaMfjPonJXFMQgFqui',
        ]);

        foreach (TokenAuth::GUARDS_TOKEN_TYPES as $type) {
            $plainTextToken = Str::random(32);

            AuthToken::forceCreate([
                'tokenable_id' => $user->id,
                'tokenable_type' => TestUserNoTokens::class,
                'type' => $type,
                'name' => 'TestName',
                'token' => hash('sha256', $plainTextToken),
                'abilities' => ['*'],
            ]);

            $request = $this->createRequest($plainTextToken);
            $guard = $this->createGuard($type, $request);

            $guardUser = $guard->user();

            $this->assertNull($guardUser);
        }
    }

    public function testAuthenticateWithValidToken() {
        foreach (TokenAuth::GUARDS_TOKEN_TYPES as $type) {
            $user = $this->createUser();
            $token = $this->createToken($type, userId: $user->id);

            $this->assertNull($token->token->last_used_at);

            $request = $this->createRequest($token->plainTextToken);
            $guard = $this->createGuard($type, $request);

            $guardUser = Event::fakeFor(function () use ($token, $guard) {
                $guardUser = $guard->user();

                Event::assertDispatched(function (
                    TokenAuthenticated $event
                ) use ($token) {
                    return $token->token->id === $event->token->id;
                });

                return $guardUser;
            });

            $this->assertNotNull($guardUser);
            $this->assertEquals($user->id, $guardUser->id);

            $token->token->refresh();

            $this->assertNotNull($token->token->last_used_at);
            $this->assertEqualsWithDelta(
                now()->timestamp,
                $token->token->last_used_at->timestamp,
                1
            );
        }
    }

    public function testAuthenticateGuardWithInvalidType() {
        $request = $this->createRequest('test_token');

        $guard = $this->createGuard('invalid_type', $request);

        $this->assertThrows(function () use ($guard) {
            $guard->authenticate();
        });
    }

    public function testAuthenticateWithCustomHeader() {
        $user = $this->createUser();

        $headerName = 'X-Custom-Token-Header';

        TokenAuth::getAuthTokenFromRequestUsing(
            fn($request) => $request->header($headerName)
        );

        foreach (TokenAuth::GUARDS_TOKEN_TYPES as $type) {
            $token = $this->createToken($type, userId: $user->id);

            $request = Request::create('/', 'GET');
            $request->headers->set($headerName, $token->plainTextToken);

            $guard = $this->createGuard($type, $request);

            $guardUser = $guard->user();
            $this->assertNotNull($guardUser);
            $this->assertEquals($user->id, $guardUser->id);
        }

        // reset for next tests
        TokenAuth::$authTokenRetrievalCallback = null;
    }

    public function testAuthenticateWithCustomHeaderAndTokenInAuthorizationHeader() {
        $user = $this->createUser();

        $headerName = 'X-Custom-Token-Header';

        TokenAuth::getAuthTokenFromRequestUsing(
            fn($request) => $request->header($headerName)
        );

        foreach (TokenAuth::GUARDS_TOKEN_TYPES as $type) {
            $token = $this->createToken($type, userId: $user->id);

            $request = $this->createRequest($token->plainTextToken);
            $guard = $this->createGuard($type, $request);

            $guardUser = $guard->user();
            $this->assertNull($guardUser);
        }

        // reset for next tests
        TokenAuth::$authTokenRetrievalCallback = null;
    }

    public function testAuthenticateExpiredTokenWithAuthenticationCallbackAlwaysTrue() {
        $user = $this->createUser();

        TokenAuth::authenticateAuthTokensUsing(function ($token, $isValid) {
            return true;
        });

        foreach (TokenAuth::GUARDS_TOKEN_TYPES as $type) {
            $token = $this->createToken($type, userId: $user->id, save: false);

            $token->token->forceFill(['expires_at' => now()])->save();

            $request = $this->createRequest($token->plainTextToken);
            $guard = $this->createGuard($type, $request);

            $guardUser = $guard->user();
            $this->assertNotNull($guardUser);
            $this->assertEquals($user->id, $guardUser->id);
        }

        // reset for next tests
        TokenAuth::$authTokenAuthenticationCallback = null;
    }

    public function testAuthenticateWithAuthenticationCallbackAlwaysFalse() {
        $user = $this->createUser();

        TokenAuth::authenticateAuthTokensUsing(function ($token, $isValid) {
            return false;
        });

        foreach (TokenAuth::GUARDS_TOKEN_TYPES as $type) {
            $token = $this->createToken($type, userId: $user->id);

            $request = $this->createRequest($token->plainTextToken);
            $guard = $this->createGuard($type, $request);

            $guardUser = $guard->user();
            $this->assertNull($guardUser);
        }

        // reset for next tests
        TokenAuth::$authTokenAuthenticationCallback = null;
    }

    public function testValidateCredentialsWithGuard() {
        $guard = $this->createGuard(
            TokenAuth::TYPE_ACCESS,
            $this->createRequest('token')
        );

        $user = $this->createUser();
        $token = $this->createToken(TokenAuth::TYPE_ACCESS, userId: $user->id);

        $this->assertNull($guard->user());

        $isValid = $guard->validate([
            'request' => $this->createRequest($token->plainTextToken),
        ]);

        $this->assertTrue($isValid);

        $isNotValid = $guard->validate([
            'request' => $this->createRequest('no_token'),
        ]);

        $this->assertFalse($isNotValid);
    }

    public function testUserIsNotRetrievedWhenAuthenticationWasTriedBefore() {
        $user = $this->createUser();
        $token = $this->createToken(TokenAuth::TYPE_ACCESS, userId: $user->id);

        $guard = $this->createGuard(
            TokenAuth::TYPE_ACCESS,
            $this->createRequest($token->plainTextToken)
        );

        $reflector = new ReflectionClass(TokenAuthGuard::class);
        $property = $reflector->getProperty('triedAuthentication');

        $property->setValue($guard, true);

        $this->assertNull($guard->user());
    }

    public function testRequestCanBeSetForGuard() {
        $request = $this->createRequest('token');
        $newRequest = $this->createRequest('new_token');

        $guard = $this->createGuard(TokenAuth::TYPE_ACCESS, $request);

        $reflector = new ReflectionClass(TokenAuthGuard::class);
        $property = $reflector->getProperty('request');

        $this->assertEquals($request, $property->getValue($guard));

        $guard->setRequest($newRequest);

        $this->assertEquals($newRequest, $property->getValue($guard));
    }

    private function createGuard($type, $request) {
        return new TokenAuthGuard($type, $request);
    }

    private function createRequest($token) {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        return $request;
    }
}
