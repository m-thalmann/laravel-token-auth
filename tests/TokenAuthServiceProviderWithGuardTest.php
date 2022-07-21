<?php

namespace TokenAuth\Tests;

use Illuminate\Auth\RequestGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Orchestra\Testbench\TestCase;
use ReflectionClass;
use TokenAuth\Guard;
use TokenAuth\Tests\Helpers\Traits\SetsUpDatabase;
use TokenAuth\TokenAuthServiceProvider;

/**
 * @covers \TokenAuth\TokenAuthServiceProvider
 * @uses \TokenAuth\Guard
 * @uses \TokenAuth\Models\AuthToken
 */
class TokenAuthServiceProviderWithGuardTest extends TestCase {
    use SetsUpDatabase, RefreshDatabase;

    public function testGuardIsResetOnRequestChange() {
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer token1');

        $this->app->bind('request', fn() => $request);

        $this->getProvider();

        $guard = Auth::getFacadeRoot()->guard('token');

        $guardReflection = new ReflectionClass(RequestGuard::class);
        $callbackProperty = $guardReflection->getProperty('callback');
        $callbackProperty->setAccessible(true);

        /**
         * @var Guard
         */
        $tokenGuard = $callbackProperty->getValue($guard);

        $tokenGuardReflection = new ReflectionClass(Guard::class);
        $triedAuthenticationProperty = $tokenGuardReflection->getProperty(
            'triedAuthentication'
        );
        $triedAuthenticationProperty->setAccessible(true);

        $this->assertFalse($triedAuthenticationProperty->getValue($tokenGuard));

        $guard->check();

        $this->assertTrue($triedAuthenticationProperty->getValue($tokenGuard));

        $newRequest = Request::create('/', 'GET');
        $newRequest->headers->set('Authorization', 'Bearer token2');

        $this->app->bind('request', fn() => $newRequest);

        $this->assertFalse($triedAuthenticationProperty->getValue($tokenGuard));
    }

    protected function getPackageProviders($app) {
        return [TokenAuthServiceProvider::class];
    }

    private function getProvider(): TokenAuthServiceProvider {
        return $this->app->getProvider(TokenAuthServiceProvider::class);
    }
}
