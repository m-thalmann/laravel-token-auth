<?php

namespace TokenAuth\Tests\Unit\Enums;

use TokenAuth\Tests\TestCase;
use TokenAuth\Enums\TokenType;

/**
 * @covers \TokenAuth\Enums\TokenType
 *
 * @uses \TokenAuth\Facades\TokenAuth
 * @uses \TokenAuth\TokenAuthManager
 * @uses \TokenAuth\TokenAuthServiceProvider
 */
class TokenTypeTest extends TestCase {
    /**
     * @dataProvider tokenTypeProvider
     */
    public function testGetGuardNameReturnsCorrectValue(
        TokenType $tokenType
    ): void {
        $this->assertEquals(
            'token-' . $tokenType->value,
            $tokenType->getGuardName()
        );
    }
}
