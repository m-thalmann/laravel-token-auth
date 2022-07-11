<?php

namespace TokenAuth\Tests\Helpers\Traits;

use Illuminate\Support\Str;
use TokenAuth\Tests\Helpers\Models\TestUser;

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
