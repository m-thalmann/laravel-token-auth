<?php

namespace TokenAuth\Tests;

use InvalidArgumentException;
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

    public function testTokenIsSavedAfter() {
        $middleware = new SaveAuthToken();

        $request = Mockery::mock();
        $user = Mockery::mock();
        $token = Mockery::mock();

        $request->shouldReceive('user')->andReturn($user);
        $user->shouldReceive('currentToken')->andReturn($token);
        $token->isSaved = false;
        $token->shouldReceive('save')->andSet('isSaved', true);

        $response = $middleware->handle(
            $request,
            function ($request) {
                $this->assertFalse($request->user()->currentToken()->isSaved);
                return 'response';
            },
            'after'
        );

        $this->assertEquals('response', $response);
        $this->assertTrue($token->isSaved);
    }

    public function testTokenIsSavedBefore() {
        $middleware = new SaveAuthToken();

        $request = Mockery::mock();
        $user = Mockery::mock();
        $token = Mockery::mock();

        $request->shouldReceive('user')->andReturn($user);
        $user->shouldReceive('currentToken')->andReturn($token);
        $token->shouldReceive('save')->andSet('isSaved', true);

        $response = $middleware->handle(
            $request,
            function ($request) {
                $this->assertTrue($request->user()->currentToken()->isSaved);
                return 'response';
            },
            'before'
        );

        $this->assertEquals('response', $response);
    }

    public function testInvalidWhenArgument() {
        $this->expectException(InvalidArgumentException::class);

        $middleware = new SaveAuthToken();

        $middleware->handle(null, null, 'invalid');
    }
}
