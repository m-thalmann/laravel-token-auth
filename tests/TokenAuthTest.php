<?php

namespace TokenAuth\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\TokenAuth;

/**
 * @covers \TokenAuth\TokenAuth
 */
class TokenAuthTest extends TestCase {
    public static function tearDownAfterClass(): void {
        // reset static values for next tests

        TokenAuth::$authTokenModel = \TokenAuth\Models\AuthToken::class;
        TokenAuth::$authTokenRetrievalCallback = null;
        TokenAuth::$authTokenAuthenticationCallback = null;
        TokenAuth::$runsMigrations = true;
    }

    public function testUseAuthTokenModel() {
        $model = Mockery::mock(Model::class, AuthTokenContract::class);

        TokenAuth::useAuthTokenModel($model);

        $this->assertEquals($model, TokenAuth::$authTokenModel);
    }

    public function testUseAuthTokenModelNoContract() {
        $this->expectException(InvalidArgumentException::class);

        TokenAuth::useAuthTokenModel(Mockery::mock(Model::class));
    }

    public function testUseAuthTokenModelNoModel() {
        $this->expectException(InvalidArgumentException::class);

        TokenAuth::useAuthTokenModel(Mockery::mock(AuthTokenContract::class));
    }

    public function testGetAuthTokenFromRequestUsing() {
        TokenAuth::getAuthTokenFromRequestUsing(function (Request $request) {
            return $request->header('X-Auth-Token');
        });

        $token = 'my_auth_token';
        $request = $this->mockRequestWithHeaders([
            'X-Auth-Token' => $token,
        ]);

        $this->assertEquals(
            $token,
            (TokenAuth::$authTokenRetrievalCallback)($request)
        );
    }

    public function testAuthenticateAuthTokenUsing() {
        TokenAuth::authenticateAuthTokensUsing(function ($token, $isValid) {
            return true;
        });

        $this->assertTrue(
            (TokenAuth::$authTokenAuthenticationCallback)(null, false)
        );
    }

    public function testIgnoreMigrations() {
        $this->assertTrue(TokenAuth::$runsMigrations);

        TokenAuth::ignoreMigrations();

        $this->assertFalse(TokenAuth::$runsMigrations);
    }

    private function mockRequestWithHeaders($headers) {
        /**
         * @var \Mockery\MockInterface|\Mockery\LegacyMockInterface
         */
        $mock = Mockery::mock(Request::class);

        foreach ($headers as $header => $value) {
            $mock
                ->shouldReceive('header')
                ->with($header)
                ->andReturn($value);
        }

        return $mock;
    }
}
