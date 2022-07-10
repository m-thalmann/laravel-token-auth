<?php

namespace TokenAuth\Tests\Helpers\Traits;

use TokenAuth\NewAuthToken;
use TokenAuth\TokenAuth;
use Illuminate\Support\Str;
use TokenAuth\Models\AuthToken;
use TokenAuth\Tests\Helpers\TestUser;

trait CanCreateToken {
    private function createToken(
        $type = TokenAuth::TYPE_ACCESS,
        $groupId = null,
        $abilities = ['*'],
        $save = true,
        $userId = 1
    ) {
        $plainTextToken = Str::random(64);

        $token = new AuthToken();

        $token->forceFill([
            'type' => $type,
            'group_id' => $groupId,
            'name' => 'TestTokenName',
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => null,
            'tokenable_type' => TestUser::class,
            'tokenable_id' => $userId,
        ]);

        if ($save) {
            $token->save();
        }

        return new NewAuthToken($token, $plainTextToken);
    }
}
