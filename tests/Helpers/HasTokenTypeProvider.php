<?php

namespace TokenAuth\Tests\Helpers;

use TokenAuth\Enums\TokenType;

trait HasTokenTypeProvider {
    public function tokenTypeProvider(): array {
        return array_map(fn(TokenType $type) => [$type], TokenType::cases());
    }
}
