<?php

namespace TokenAuth\Tests;

use Illuminate\Auth\AuthenticationException;
use Mockery;
use Orchestra\Testbench\TestCase;
use TokenAuth\Exceptions\MissingAbilityException;
use TokenAuth\Http\Middleware\CheckAbilities;

/**
 * @covers \TokenAuth\Http\Middleware\CheckAbilities
 * @covers \TokenAuth\Exceptions\MissingAbilityException
 */
class CheckAbilitiesTest extends TestCase {
    protected function tearDown(): void {
        parent::tearDown();

        Mockery::close();
    }

    public function testRequestIsPassedAlongIfAllAbilitiesArePresent() {
        $middleware = new CheckAbilities();

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
            ->andReturn(true);

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

        $middleware = new CheckAbilities();

        $request = Mockery::mock();
        $request->shouldReceive('user')->andReturn(null);

        $middleware->handle($request, fn() => null, 'foo');
    }

    public function testExceptionIfNotAllAbilitiesArePresent() {
        $this->expectException(MissingAbilityException::class);

        $middleware = new CheckAbilities();

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
            $middleware->handle($request, fn() => null, 'foo', 'bar');
        } catch (MissingAbilityException $e) {
            $this->assertEquals(1, count($e->abilities()));
            $this->assertEquals('bar', $e->abilities()[0]);
            throw $e;
        }
    }
}
