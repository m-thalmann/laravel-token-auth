<?php

namespace TokenAuth\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use TokenAuth\Models\AuthToken;
use TokenAuth\Tests\Helpers\Traits\CanCreateToken;
use TokenAuth\Tests\Helpers\Traits\CanCreateUser;
use TokenAuth\Tests\Helpers\Traits\SetsUpDatabase;
use TokenAuth\TokenAuth;

/**
 * @covers \TokenAuth\Models\AuthToken::prunable()
 * @uses \TokenAuth\NewAuthToken
 * @uses \TokenAuth\Traits\HasAuthTokens
 * @uses \TokenAuth\TokenAuthServiceProvider
 */
class PruneTokensTest extends TestCase {
    use SetsUpDatabase, RefreshDatabase, CanCreateUser, CanCreateToken;

    /**
     * @param bool $testWithExpired Whether to test pruning expired or revoked tokens
     *
     * @dataProvider booleanProvider
     */
    public function testPruneExpiredTokens($testWithExpired) {
        $user = $this->createUser();
        $accessTokenHours = config('tokenAuth.token_prune_after_hours.access');
        $refreshTokenHours = config(
            'tokenAuth.token_prune_after_hours.refresh'
        );

        $createdTokens = [];

        for ($i = 0; $i < 3; $i++) {
            $testAccessHours = $accessTokenHours - $i + 1; // 1 before, 1 exactly, 1 after (since $i = 0,1,2)
            $testRefreshHours = $refreshTokenHours - $i + 1; // 1 before, 1 exactly, 1 after (since $i = 0,1,2)

            if ($testWithExpired) {
                $createdTokens = array_merge(
                    $createdTokens,
                    $this->createTokensWithInfo(
                        userId: $user->id,
                        expiredHours: [$testAccessHours, $testRefreshHours]
                    )
                );
            } else {
                $createdTokens = array_merge(
                    $createdTokens,
                    $this->createTokensWithInfo(
                        userId: $user->id,
                        revokedHours: [$testAccessHours, $testRefreshHours]
                    )
                );
            }
        }

        $this->assertEquals(count($createdTokens), $user->tokens()->count());

        $this->assertEquals(
            0,
            Artisan::call('model:prune', [
                '--model' => [AuthToken::class],
            ])
        );

        // 1 token expired for only hours - 1 (for each type => 2)
        $this->assertEquals(2, $user->tokens()->count());

        Artisan::call('model:prune', [
            '--model' => [AuthToken::class],
        ]);
    }

    public function booleanProvider() {
        return [[true], [false]];
    }

    private function createTokensWithInfo(
        $userId,
        $expiredHours = null,
        $revokedHours = null
    ) {
        $tokens = [];
        foreach (
            [TokenAuth::TYPE_ACCESS, TokenAuth::TYPE_REFRESH]
            as $index => $type
        ) {
            $expiredHoursForType = Arr::get($expiredHours, $index);
            $revokedHoursForType = Arr::get($revokedHours, $index);

            $tokens[] = $this->createTokenWithInfo(
                $type,
                $userId,
                $expiredHoursForType,
                $revokedHoursForType
            );
        }

        return $tokens;
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
