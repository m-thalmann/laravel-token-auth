<?php

namespace TokenAuth\Tests\Unit;

use Orchestra\Testbench\TestCase;
use TokenAuth\Contracts\TokenAuthManagerContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Support\AbstractTokenGuard;
use TokenAuth\Tests\Helpers\UsesPackageProvider;

/**
 * @covers \TokenAuth\TokenAuthServiceProvider
 * @covers \TokenAuth\Facades\TokenAuth
 *
 * @uses \TokenAuth\Enums\TokenType
 * @uses \TokenAuth\Support\AbstractTokenGuard
 * @uses \TokenAuth\TokenAuthManager
 */
class TokenAuthServiceProviderTest extends TestCase {
    use UsesPackageProvider;

    public function testRegistersTokenAuthManagerSingleton(): void {
        $this->assertInstanceOf(
            TokenAuthManagerContract::class,
            $this->app->get(TokenAuthManagerContract::class)
        );

        $this->assertSame(
            $this->app->get(TokenAuthManagerContract::class),
            TokenAuth::getFacadeRoot()
        );
    }

    public function testRegistersAndConfiguresGuards(): void {
        /**
         * @var \Illuminate\Auth\AuthManager
         */
        $auth = $this->app->get('auth');

        foreach (TokenType::cases() as $tokenType) {
            $guard = $auth->guard($tokenType->getGuardName());

            $this->assertNotNull($guard);
            $this->assertInstanceOf(AbstractTokenGuard::class, $guard);
        }
    }
}
