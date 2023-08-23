<?php

namespace TokenAuth\Tests\Helpers;

use TokenAuth\Facades\TokenAuth;
use TokenAuth\TokenAuthServiceProvider;

trait UsesPackageProvider {
    protected function getPackageProviders(mixed $app): array {
        return [TokenAuthServiceProvider::class];
    }

    protected function getPackageAliases(mixed $app): array {
        return [
            'TokenAuth' => TokenAuth::class,
        ];
    }
}
