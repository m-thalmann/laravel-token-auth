<?php

namespace TokenAuth\Tests\Helpers\Models;

use Illuminate\Foundation\Auth\User;

class TestUserNoTokens extends User {
    protected $guarded = [];

    protected $table = 'users';
}
