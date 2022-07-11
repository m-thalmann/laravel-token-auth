<?php

namespace TokenAuth\Tests\Helpers;

use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\TokenAuth;

class TestAuthToken implements AuthTokenContract {
    public function deleteAllTokensFromSameGroup() {
    }

    public function revoke() {
        return $this;
    }

    public function isRevoked() {
        return false;
    }

    public function save(array $options = []) {
        return true;
    }

    public function getType() {
        return TokenAuth::TYPE_ACCESS;
    }

    public static function findAccessToken($token) {
        return null;
    }

    public static function findRefreshToken($token) {
        return null;
    }

    public function can($ability) {
        return false;
    }

    public function cant($ability) {
        return true;
    }
}
