<?php

namespace TokenAuth\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use TokenAuth\Enums\TokenType;
use TokenAuth\Tests\Helpers\HasTokenTypeProvider;

/**
 * @covers \TokenAuth\Enums\TokenType
 */
class TokenTypeTest extends TestCase {
    use HasTokenTypeProvider;

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
