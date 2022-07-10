<?php

namespace TokenAuth\Tests;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Orchestra\Testbench\TestCase;
use TokenAuth\Tests\Helpers\Traits\CanCreateToken;
use TokenAuth\Tests\Helpers\Traits\CanCreateUser;
use TokenAuth\Tests\Helpers\Traits\SetsUpDatabase;
use TokenAuth\TokenAuth;

/**
 * @covers \TokenAuth\Console\Commands\PruneExpiredTokens
 * @uses \TokenAuth\NewAuthToken
 * @uses \TokenAuth\Traits\HasAuthTokens
 * @uses \TokenAuth\TokenAuthServiceProvider
 */
class PruneExpiredTokensTest extends TestCase {
    use SetsUpDatabase, RefreshDatabase, CanCreateUser, CanCreateToken;

    /**
     * @dataProvider typeAndCriteriaProvider
     */
    public function testPruneTokensOfOneTypeAndCriteria($type, $criteria) {
        $isExpiredTest = $criteria === 'expired';

        $user = $this->createUser();
        $hours = 12;

        for ($i = 0; $i < 3; $i++) {
            $testHours = $hours - $i + 1; // 1 before, 1 exactly, 1 after (since $i = 0,1,2)

            if ($isExpiredTest) {
                $this->createTokenWithInfo(
                    $type,
                    userId: $user->id,
                    expiredHours: $testHours
                );
            } else {
                $this->createTokenWithInfo(
                    $type,
                    userId: $user->id,
                    revokedHours: $testHours
                );
            }
        }

        if ($isExpiredTest) {
            $this->createTokenWithInfo(
                $type === TokenAuth::TYPE_ACCESS
                    ? TokenAuth::TYPE_REFRESH
                    : TokenAuth::TYPE_ACCESS,
                userId: $user->id,
                expiredHours: $hours * 2
            );
        } else {
            $this->createTokenWithInfo(
                $type === TokenAuth::TYPE_ACCESS
                    ? TokenAuth::TYPE_REFRESH
                    : TokenAuth::TYPE_ACCESS,
                userId: $user->id,
                revokedHours: $hours * 2
            );
        }

        $this->assertEquals(4, $user->tokens()->count());

        $this->artisan('tokenAuth:prune-expired', [
            'type' => $type,
            '--hours' => $hours,
        ])
            ->expectsOutput(
                "Tokens expired/revoked for more than $hours hours pruned successfully."
            )
            ->assertSuccessful();

        // 1 other type token, 1 token expired for only hours - 1
        $this->assertEquals(2, $user->tokens()->count());
    }

    /**
     * @dataProvider typeProvider
     */
    public function testPruneRevokedAndExpiredTokensOfType($type) {
        $otherType =
            $type === TokenAuth::TYPE_ACCESS
                ? TokenAuth::TYPE_REFRESH
                : TokenAuth::TYPE_ACCESS;

        $user = $this->createUser();
        $hours = 12;

        for ($i = 0; $i < 3; $i++) {
            $testHours = $hours - $i + 1; // 1 before, 1 exactly, 1 after (since $i = 0,1,2)

            $this->createTokenWithInfo(
                $type,
                userId: $user->id,
                expiredHours: $testHours
            );
        }
        for ($i = 0; $i < 3; $i++) {
            $testHours = $hours - $i + 1; // 1 before, 1 exactly, 1 after (since $i = 0,1,2)

            $this->createTokenWithInfo(
                $type,
                userId: $user->id,
                revokedHours: $testHours
            );
        }

        $this->createTokenWithInfo(
            $otherType,
            userId: $user->id,
            expiredHours: $hours * 2
        );
        $this->createTokenWithInfo(
            $otherType,
            userId: $user->id,
            revokedHours: $hours * 2
        );

        $this->assertEquals(8, $user->tokens()->count());

        $this->artisan('tokenAuth:prune-expired', [
            'type' => $type,
            '--hours' => $hours,
        ])
            ->expectsOutput(
                "Tokens expired/revoked for more than $hours hours pruned successfully."
            )
            ->assertSuccessful();

        // 2 other type tokens, 2 tokens expired for only hours - 1 (-> access + refresh)
        $this->assertEquals(4, $user->tokens()->count());
    }

    public function testBadType() {
        $this->artisan('tokenAuth:prune-expired', [
            'type' => 'no type',
        ])->assertExitCode(Command::INVALID);
    }

    public function typeAndCriteriaProvider() {
        return Arr::crossJoin(
            [TokenAuth::TYPE_ACCESS, TokenAuth::TYPE_REFRESH],
            ['expired', 'revoked']
        );
    }

    public function typeProvider() {
        return [[TokenAuth::TYPE_ACCESS], [TokenAuth::TYPE_REFRESH]];
    }

    private function createTokenWithInfo(
        $type,
        $userId,
        $expiredHours = null,
        $revokedHours = null
    ) {
        $token = $this->createToken($type, userId: $userId, save: false);

        if ($expiredHours !== null) {
            $token->token->expires_at = now()->subHours($expiredHours);
        }
        if ($revokedHours !== null) {
            $token->token->revoked_at = now()->subHours($revokedHours);
        }

        $token->token->save();

        return $token;
    }
}
