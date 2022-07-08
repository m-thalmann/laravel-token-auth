<?php

namespace TokenAuth\Tests\Helpers;

use Illuminate\Foundation\Auth\User;
use TokenAuth\Traits\HasAuthTokens;

class TestUser extends User {
    use HasAuthTokens;

    protected $guarded = [];

    protected $table = 'users';
}
