<?php

namespace TokenAuth\Tests;

use Illuminate\Auth\AuthenticationException;
use Mockery;
use Orchestra\Testbench\TestCase;
use TokenAuth\Exceptions\MissingAbilityException;
use TokenAuth\Http\Middleware\CheckForAnyAbility;

/**
 * @covers \TokenAuth\Http\Middleware\CheckForAnyAbility
 * @covers \TokenAuth\Exceptions\MissingAbilityException
 */
class CheckForAnyAbilityTest extends TestCase {
    protected function tearDown(): void {
        parent::tearDown();

        Mockery::close();
    }

    public function testRequestIsPassedAlongIfOneOfAbilityIsPresent() {
        $middleware = new CheckForAnyAbility();

        $request = Mockery::mock();
        $user = Mockery::mock();
        $token = Mockery::mock();

        $userId = 1;

        $request->shouldReceive('user')->andReturn($user);

        $user->shouldReceive('getKey')->andReturn($userId);
        $user->shouldReceive('currentToken')->andReturn($token);
        $user
            ->shouldReceive('tokenCan')
            ->with('foo')
            ->andReturn(true);
        $user
            ->shouldReceive('tokenCan')
            ->with('bar')
            ->andReturn(false);

        $response = $middleware->handle(
            $request,
            fn($request) => $request->user()->getKey(),
            'foo',
            'bar'
        );

        $this->assertEquals($userId, $response);
    }

    public function testExceptionIfNotAuthenticated() {
        $this->expectException(AuthenticationException::class);

        $middleware = new CheckForAnyAbility();

        $request = Mockery::mock();
        $request->shouldReceive('user')->andReturn(null);

        $middleware->handle($request, fn() => null, 'foo');
    }

    public function testExceptionIfNoAbilityIsPresent() {
        $this->expectException(MissingAbilityException::class);

        $middleware = new CheckForAnyAbility();

        $request = Mockery::mock();
        $user = Mockery::mock();
        $token = Mockery::mock();

        $request->shouldReceive('user')->andReturn($user);

        $user->shouldReceive('currentToken')->andReturn($token);
        $user
            ->shouldReceive('tokenCan')
            ->with('foo')
            ->andReturn(true);
        $user
            ->shouldReceive('tokenCan')
            ->with('bar')
            ->andReturn(false);

        try {
            $middleware->handle($request, fn() => null, 'bar');
        } catch (MissingAbilityException $e) {
            $this->assertEquals(1, count($e->abilities()));
            $this->assertEquals('bar', $e->abilities()[0]);
            throw $e;
        }
    }
}
