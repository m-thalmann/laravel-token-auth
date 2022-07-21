<?php

namespace TokenAuth\Tests;

use Illuminate\Support\Facades\Auth;
use Orchestra\Testbench\TestCase;
use TokenAuth\TokenAuth;
use TokenAuth\TokenAuthServiceProvider;

/**
 * @covers \TokenAuth\TokenAuthServiceProvider
 * @uses \TokenAuth\TokenAuthGuard
 */
class TokenAuthServiceProviderTest extends TestCase {
    public function testInstantiation() {
        $provider = $this->getProvider();

        $expectations = [
            \Illuminate\Support\ServiceProvider::class,
            TokenAuthServiceProvider::class,
        ];

        foreach ($expectations as $expected) {
            $this->assertInstanceOf($expected, $provider);
        }
    }

    public function testRegistersGuards() {
        $this->getProvider();

        /**
         * @var \Illuminate\Auth\AuthManager
         */
        $auth = Auth::getFacadeRoot();

        foreach (TokenAuth::GUARDS_TOKEN_TYPES as $guard => $type) {
            // test if the guard exists
            $auth->guard($guard);
            $this->addToAssertionCount(1);
        }
    }

    protected function getPackageProviders($app) {
        return [TokenAuthServiceProvider::class];
    }

    private function getProvider(): TokenAuthServiceProvider {
        return $this->app->getProvider(TokenAuthServiceProvider::class);
    }
}
