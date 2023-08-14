<?php

namespace TokenAuth\Tests\Helpers;

use TokenAuth\Facades\TokenAuth;
use TokenAuth\TokenAuthServiceProvider;

trait UsesPackageProvider {
    protected function getPackageProviders($app) {
        return [TokenAuthServiceProvider::class];
    }

    protected function getPackageAliases($app) {
        return [
            'TokenAuth' => TokenAuth::class,
        ];
    }
}
