<?php

namespace TokenAuth\Tests;

use PHPUnit\Framework\TestCase;
use TokenAuth\NewAuthToken;
use TokenAuth\Tests\Helpers\TestAuthToken;

/**
 * @covers \TokenAuth\NewAuthToken
 */
class NewAuthTokenTest extends TestCase {
    public function testCreateInstance() {
        $token = new TestAuthToken();
        $plainTextToken = 'my_test_token';

        $newAuthToken = new NewAuthToken($token, $plainTextToken);

        $this->assertEquals($token, $newAuthToken->token);
        $this->assertEquals($plainTextToken, $newAuthToken->plainTextToken);
    }

    public function testToArray() {
        $token = new TestAuthToken();
        $plainTextToken = 'my_test_token';

        $newAuthToken = new NewAuthToken($token, $plainTextToken);

        $array = $newAuthToken->toArray();

        $this->assertIsArray($array);

        $this->assertArrayHasKey('token', $array);
        $this->assertArrayHasKey('plainTextToken', $array);

        $this->assertEquals($token, $array['token']);
        $this->assertEquals($plainTextToken, $array['plainTextToken']);
    }

    public function testToJson() {
        $token = new TestAuthToken();
        $plainTextToken = 'my_test_token';

        $newAuthToken = new NewAuthToken($token, $plainTextToken);

        $json = $newAuthToken->toJson();

        $this->assertJson($json);
    }
}
