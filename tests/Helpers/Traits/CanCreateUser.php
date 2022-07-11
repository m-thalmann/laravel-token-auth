<?php

namespace TokenAuth\Tests\Helpers\Traits;

use TokenAuth\Tests\Helpers\TestUser;
use Illuminate\Support\Str;

trait CanCreateUser {
    private function createUser(): TestUser {
        return TestUser::create([
            'name' => 'John Doe',
            'email' => Str::random(10) . '@example.com',
            'password' =>
                '$2a$12$CV9PJXeDrEcLHlC0kVlQcemiQ/CFt5jgVEXtaMfjPonJXFMQgFqui', // 123
        ]);
    }
}
