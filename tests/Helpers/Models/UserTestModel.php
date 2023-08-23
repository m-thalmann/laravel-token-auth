<?php

namespace TokenAuth\Tests\Helpers\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;

class UserTestModel extends User implements Authenticatable {
    public $table = 'users';
}
