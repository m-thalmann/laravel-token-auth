<?php

namespace TokenAuth\Tests\Helpers;

use TokenAuth\Facades\TokenAuth;
use TokenAuth\TokenAuthServiceProvider;

trait UsesPackageProvider {
    protected function getPackageProviders($app): array {
        return [TokenAuthServiceProvider::class];
    }

    protected function getPackageAliases($app): array {
        return [
            'TokenAuth' => TokenAuth::class,
        ];
    }
}
