<?php

namespace TokenAuth\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use TokenAuth\Enums\TokenType;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

class TestCase extends OrchestraTestCase {
    use WithWorkbench;

    protected function createTestUser(): User {
        /** @var User */
        $user = UserFactory::new()->create();

        return $user;
    }

    public static function tokenTypeProvider(): array {
        return array_map(fn(TokenType $type) => [$type], TokenType::cases());
    }
}
