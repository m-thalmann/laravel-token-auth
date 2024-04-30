<?php

namespace Workbench\App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User as BaseUser;

class User extends BaseUser implements Authenticatable {
    public $table = 'users';
}
