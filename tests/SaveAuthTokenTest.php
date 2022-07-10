<?php

namespace TokenAuth\Tests;

use Mockery;
use PHPUnit\Framework\TestCase;
use TokenAuth\Http\Middleware\SaveAuthToken;

/**
 * @covers \TokenAuth\Http\Middleware\SaveAuthToken
 */
class SaveAuthTokenTest extends TestCase {
    protected function tearDown(): void {
        parent::tearDown();

        Mockery::close();
    }

    public function testTokenIsSaved() {
        $middleware = new SaveAuthToken();

        $request = Mockery::mock();
        $user = Mockery::mock();
        $token = Mockery::mock();

        $request->shouldReceive('user')->andReturn($user);
        $user->shouldReceive('currentToken')->andReturn($token);
        $token->shouldReceive('save');

        $response = $middleware->handle($request, fn() => 'response');

        $this->assertEquals('response', $response);
    }
}
