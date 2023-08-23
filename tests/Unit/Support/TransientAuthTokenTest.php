<?php

namespace TokenAuth\Tests\Unit\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use TokenAuth\Enums\TokenType;
use TokenAuth\Support\TransientAuthToken;

/**
 * @covers \TokenAuth\Support\TransientAuthToken
 */
class TransientAuthTokenTest extends TestCase {
    private TransientAuthToken|MockInterface $token;

    protected function setUp(): void {
        parent::setUp();

        /**
         * @var TransientAuthToken|MockInterface
         */
        $this->token = Mockery::mock(TransientAuthTokenTestClass::class);

        $this->token->makePartial();
    }

    public function testGetTypeReturnsTypeProperty(): void {
        $this->token->type = TokenType::ACCESS;

        $this->assertEquals($this->token->type, $this->token->getType());
    }

    public function testGetAuthenticatableReturnsAuthenticatableProperty(): void {
        $testUser = Mockery::mock(Authenticatable::class);

        $this->token->authenticatable = $testUser;

        $this->assertSame($testUser, $this->token->getAuthenticatable());
    }

    public function testGetGroupIdReturnsGroupIdProperty(): void {
        $this->token->groupId = 123;

        $this->assertEquals($this->token->groupId, $this->token->getGroupId());
    }

    public function testGetNameReturnsNameProperty(): void {
        $this->token->name = 'test name';

        $this->assertEquals($this->token->name, $this->token->getName());
    }

    public function testGetAbilitiesReturnsAbilitiesProperty(): void {
        $this->token->abilities = ['test ability'];

        $this->assertEquals(
            $this->token->abilities,
            $this->token->getAbilities()
        );
    }

    public function testGetRevokedAtReturnsNull(): void {
        $this->assertNull($this->token->getRevokedAt());
    }

    public function testGetExpiresAtReturnsExpiresAtProperty(): void {
        $testDate = now()->addWeek();

        $this->token->expiresAt = $testDate;

        $this->assertSame($testDate, $this->token->getExpiresAt());
    }

    public function testStoreThrowsLogicException(): void {
        $this->expectException(LogicException::class);
        $this->token->store();
    }

    public function testRemoveThrowsLogicException(): void {
        $this->expectException(LogicException::class);
        $this->token->remove();
    }

    public function testRevokeThrowsLogicException(): void {
        $this->expectException(LogicException::class);
        $this->token->revoke();
    }

    public function testToArrayReturnsAllPropertiesExceptTokenAsArray(): void {
        $this->token->type = TokenType::ACCESS;
        $this->token->token = 'test token';
        $this->token->authenticatable = Mockery::mock(Authenticatable::class);
        $this->token->groupId = 123;
        $this->token->name = 'test name';
        $this->token->abilities = ['test ability'];
        $this->token->expiresAt = now()->addWeek();

        $array = $this->token->toArray();

        $this->assertArrayNotHasKey('token', $array);

        $this->assertEquals(
            [
                'type' => $this->token->type,
                'authenticatable' => $this->token->authenticatable,
                'group_id' => $this->token->groupId,
                'name' => $this->token->name,
                'abilities' => $this->token->abilities,
                'expires_at' => $this->token->expiresAt,
            ],
            $array
        );
    }

    public function testFindThrowsLogicException(): void {
        $this->expectException(LogicException::class);
        TransientAuthToken::find(null, 'test token');
    }

    public function testCreateThrowsLogicException(): void {
        $this->expectException(LogicException::class);
        TransientAuthToken::create(TokenType::ACCESS);
    }

    public function testGenerateGroupIdThrowsLogicException(): void {
        $this->expectException(LogicException::class);
        TransientAuthToken::generateGroupId(
            Mockery::mock(Authenticatable::class)
        );
    }

    public function testDeleteTokensFromGroupThrowsLogicException(): void {
        $this->expectException(LogicException::class);
        TransientAuthToken::deleteTokensFromGroup(123);
    }
}

class TransientAuthTokenTestClass extends TransientAuthToken {
    public static function hashToken(string $plainTextToken): string {
        return parent::hashToken($plainTextToken);
    }
}
