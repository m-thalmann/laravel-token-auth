<?php

namespace TokenAuth\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \TokenAuth\TokenAuth
 */
class TokenAuth extends Facade {
    protected static function getFacadeAccessor() {
        return 'tokenAuth';
    }
}
