<?php

namespace TokenAuth\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use TokenAuth\Contracts\TokenAuthManagerContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Support\TokenGuard;
use TokenAuth\Tests\TestCase;
use TokenAuth\TokenAuthManager;
use TokenAuth\TokenAuthServiceProvider;

/**
 * @covers \TokenAuth\TokenAuthServiceProvider
 * @covers \TokenAuth\Facades\TokenAuth
 *
 * @uses \TokenAuth\Enums\TokenType
 * @uses \TokenAuth\Support\TokenGuard
 * @uses \TokenAuth\TokenAuthManager
 */

#[CoversClass(TokenAuthServiceProvider::class)]
#[CoversClass(TokenAuth::class)]
#[UsesClass(TokenType::class)]
#[UsesClass(TokenGuard::class)]
#[UsesClass(TokenAuthManager::class)]
class TokenAuthServiceProviderTest extends TestCase {
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
            $this->assertInstanceOf(TokenGuard::class, $guard);
        }
    }
}
