<?php

namespace TokenAuth\Tests\Helpers\Traits;

use TokenAuth\Tests\Helpers\TestUser;

trait CanCreateUser {
    private function createUser(): TestUser {
        return TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@doe.com',
            'password' =>
                '$2a$12$CV9PJXeDrEcLHlC0kVlQcemiQ/CFt5jgVEXtaMfjPonJXFMQgFqui', // 123
        ]);
    }
}
