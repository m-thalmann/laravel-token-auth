<?php

namespace TokenAuth\Tests\Unit\Enums;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use TokenAuth\Tests\TestCase;
use TokenAuth\Enums\TokenType;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\TokenAuthManager;
use TokenAuth\TokenAuthServiceProvider;

#[CoversClass(TokenType::class)]
#[UsesClass(TokenAuth::class)]
#[UsesClass(TokenAuthManager::class)]
#[UsesClass(TokenAuthServiceProvider::class)]
class TokenTypeTest extends TestCase {
    #[DataProvider('tokenTypeProvider')]
    public function testGetGuardNameReturnsCorrectValue(
        TokenType $tokenType
    ): void {
        $this->assertEquals(
            'token-' . $tokenType->value,
            $tokenType->getGuardName()
        );
    }
}
