<?php

namespace TokenAuth\Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use TokenAuth\Enums\TokenType;
use TokenAuth\Models\AuthToken;
use TokenAuth\Tests\TestCase;
use Workbench\App\Models\User;

/**
 * @coversNothing
 */
class TokenAuthRequestTest extends TestCase {
    use LazilyRefreshDatabase;

    private User $testUser;

    protected function setUp(): void {
        parent::setUp();

        $this->testUser = $this->createTestUser();
    }

    /**
     * @dataProvider tokenTypeProvider
     */
    public function testAuthenticatesRouteRequestWithValidToken(
        TokenType $tokenType
    ): void {
        $token = AuthToken::create($tokenType)
            ->setAuthenticatable($this->testUser)
            ->build();

        $route = $this->createTestRoute($tokenType->getGuardName());

        $response = $this->getJson($route, [
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ]);

        $response->assertOk();
    }

    public function testDoesNotAuthenticateRouteRequestWithInvalidToken() {
        $invalidToken = 'this-is-not-a-valid-token';

        $route = $this->createTestRoute(TokenType::ACCESS->getGuardName());

        $response = $this->getJson($route, [
            'Authorization' => 'Bearer ' . $invalidToken,
        ]);

        $response->assertUnauthorized();
    }

    public function testDoesNotAuthenticateRouteRequestWithExpiredToken() {
        $token = AuthToken::create(TokenType::ACCESS)
            ->setAuthenticatable($this->testUser)
            ->setExpiresAt(now()->subDay())
            ->build();

        $route = $this->createTestRoute(TokenType::ACCESS->getGuardName());

        $response = $this->getJson($route, [
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ]);

        $response->assertUnauthorized();
    }

    public function testDoesNotAuthenticateRouteRequestWithRevokedToken() {
        $token = AuthToken::create(TokenType::ACCESS)
            ->setAuthenticatable($this->testUser)
            ->build(save: false);

        $token->token->revoke()->store();

        $route = $this->createTestRoute(TokenType::ACCESS->getGuardName());

        $response = $this->getJson($route, [
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ]);

        $response->assertUnauthorized();
    }

    public function testDoesNotAuthenticateRouteRequestWithWrongTokenType() {
        $token = AuthToken::create(TokenType::REFRESH)
            ->setAuthenticatable($this->testUser)
            ->build();

        $route = $this->createTestRoute(TokenType::ACCESS->getGuardName());

        $response = $this->getJson($route, [
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ]);

        $response->assertUnauthorized();
    }

    private function createTestRoute(string $guardName) {
        $route = '/example-route';

        Route::get($route, function () {
            return 'OK';
        })->middleware("auth:$guardName");

        return $route;
    }
}
