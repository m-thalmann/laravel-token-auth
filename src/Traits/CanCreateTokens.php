<?php

namespace TokenAuth\Traits;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Contracts\HasAbilities;
use TokenAuth\Exceptions\MissingAbilityException;
use TokenAuth\Models\AuthToken;
use TokenAuth\TokenAuth;

trait CanCreateTokens {
    /**
     * Create a new refresh and access token for the authenticated user with the same token group
     *
     * @param string $refreshTokenName
     * @param string $accessTokenName
     * @param array<array<string>> $tokenAbilities The first value are the abilities for the refresh token, the second one for the access token
     * @param array<int|null> $tokenExpirationMinutes The first value is the expiration time for the refresh token, the second one for the access token
     * @param bool $save Whether the tokens should be saved (default true)
     *
     * @return array<\TokenAuth\NewAuthToken> The refresh token and the access token
     */
    public static function createTokenPair(
        string $refreshTokenName,
        string $accessTokenName,
        array $tokenAbilities = [['*'], ['*']],
        array $tokenExpirationMinutes = [null, null],
        bool $save = true
    ) {
        /**
         * @var HasAuthTokens
         */
        $user = auth()->user();

        if ($user === null) {
            throw new AuthorizationException();
        }

        return self::createTokenPairForUser(
            $user,
            $refreshTokenName,
            $accessTokenName,
            $tokenAbilities,
            $tokenExpirationMinutes,
            $save
        );
    }

    /**
     * Create a new refresh and access token for the user with the same token group
     *
     * @param \TokenAuth\Traits\HasAuthTokens $user
     * @param string $refreshTokenName
     * @param string $accessTokenName
     * @param array<array<string>> $tokenAbilities The first value are the abilities for the refresh token, the second one for the access token
     * @param array<int|null> $tokenExpirationMinutes The first value is the expiration time for the refresh token, the second one for the access token
     * @param bool $save Whether the tokens should be saved (default true)
     *
     * @return array<\TokenAuth\NewAuthToken> The refresh token and the access token
     */
    public static function createTokenPairForUser(
        $user,
        string $refreshTokenName,
        string $accessTokenName,
        array $tokenAbilities = [['*'], ['*']],
        array $tokenExpirationMinutes = [null, null],
        bool $save = true
    ) {
        @[$refreshTokenAbilities, $accessTokenAbilities] = $tokenAbilities;
        @[
            $refreshTokenExpiration,
            $accessTokenExpiration,
        ] = $tokenExpirationMinutes;

        $tokenGroupId = self::getNextTokenGroupId($user);

        $refreshToken = $user->createToken(
            TokenAuth::TYPE_REFRESH,
            $refreshTokenName,
            $tokenGroupId,
            $refreshTokenAbilities,
            $refreshTokenExpiration,
            $save
        );

        self::checkHasAllAbilities($refreshToken->token, $accessTokenAbilities);

        $accessToken = $user->createToken(
            TokenAuth::TYPE_ACCESS,
            $accessTokenName,
            $tokenGroupId,
            $accessTokenAbilities,
            $accessTokenExpiration,
            $save
        );

        return [$refreshToken, $accessToken];
    }

    /**
     * Create a new refresh token (with values from the current one) and access token
     * for the current user from the refresh token he used to authenticate with.
     * The current refresh token is revoked.
     *
     * @param string $accessTokenName
     * @param array $accessTokenAbilities
     * @param array<int|null> $tokenExpirationMinutes The first value is the expiration time for the refresh token, the second one for the access token
     * @param bool $save Whether the tokens should be saved (default true)
     *
     * @return array<\TokenAuth\NewAuthToken> The refresh token and the access token
     */
    public static function rotateRefreshToken(
        string $accessTokenName,
        array $accessTokenAbilities = ['*'],
        array $tokenExpirationMinutes = [null, null],
        bool $save = true
    ) {
        /**
         * @var HasAuthTokens
         */
        $user = auth()->user();

        if ($user === null || $user->currentToken() === null) {
            throw new AuthorizationException();
        }

        return self::rotateRefreshTokenForUser(
            $user,
            $user->currentToken(),
            $accessTokenName,
            $accessTokenAbilities,
            $tokenExpirationMinutes,
            $save
        );
    }

    /**
     * Create a new refresh token (with values from the given one) and access token for
     * the given user with the values from the given refresh token.
     * The given refresh token is revoked.
     *
     * @param \TokenAuth\Traits\HasAuthTokens $user
     * @param \TokenAuth\Contracts\AuthTokenContract|\Illuminate\Database\Eloquent\Model $refreshToken
     * @param string $accessTokenName
     * @param array $accessTokenAbilities
     * @param array<int|null> $tokenExpirationMinutes The first value is the expiration time for the refresh token, the second one for the access token
     * @param bool $save Whether the tokens should be saved (default true)
     *
     * @return array<\TokenAuth\NewAuthToken> The refresh token and the access token
     */
    public static function rotateRefreshTokenForUser(
        $user,
        AuthTokenContract $refreshToken,
        string $accessTokenName,
        array $accessTokenAbilities = ['*'],
        array $tokenExpirationMinutes = [null, null],
        bool $save = true
    ) {
        if ($refreshToken->getType() !== TokenAuth::TYPE_REFRESH) {
            throw new InvalidArgumentException();
        }

        self::checkHasAllAbilities($refreshToken, $accessTokenAbilities);

        @[
            $refreshTokenExpiration,
            $accessTokenExpiration,
        ] = $tokenExpirationMinutes;

        $refreshToken->revoke();

        if ($save) {
            $refreshToken->save();
        }

        $newRefreshToken = $user->createToken(
            TokenAuth::TYPE_REFRESH,
            $refreshToken->name,
            $refreshToken->group_id,
            $refreshToken->abilities,
            $refreshTokenExpiration,
            $save
        );

        $newAccessToken = $user->createToken(
            TokenAuth::TYPE_ACCESS,
            $accessTokenName,
            $refreshToken->group_id,
            $accessTokenAbilities,
            $accessTokenExpiration,
            $save
        );

        return [$newRefreshToken, $newAccessToken];
    }

    /**
     * Create a new access token for the authenticated user without a token group
     *
     * @param string $name
     * @param array $abilities
     * @param int|null $expiresInMinutes
     * @param bool $save Whether the token should be saved (default true)
     *
     * @return \TokenAuth\NewAuthToken
     */
    public static function createAccessToken(
        string $name,
        array $abilities = ['*'],
        int|null $expiresInMinutes = null,
        bool $save = true
    ) {
        /**
         * @var HasAuthTokens
         */
        $user = auth()->user();

        if ($user === null) {
            throw new AuthorizationException();
        }

        return self::createAccessTokenForUser(
            $user,
            $name,
            $abilities,
            $expiresInMinutes,
            $save
        );
    }

    /**
     * Create a new access token for the given user without a token group
     *
     * @param \TokenAuth\Traits\HasAuthTokens $user
     * @param string $name
     * @param array $abilities
     * @param int|null $expiresInMinutes
     * @param bool $save Whether the token should be saved (default true)
     *
     * @return \TokenAuth\NewAuthToken
     */
    public static function createAccessTokenForUser(
        $user,
        string $name,
        array $abilities = ['*'],
        int|null $expiresInMinutes = null,
        bool $save = true
    ) {
        return $user->createToken(
            TokenAuth::TYPE_ACCESS,
            $name,
            null,
            $abilities,
            $expiresInMinutes,
            $save
        );
    }

    /**
     * Return the id for the next token group.
     * If no group is found in the database 1 is returned
     *
     * @param \TokenAuth\Traits\HasAuthTokens $user The tokenable entity for which to retrieve the group_id
     *
     * @return int
     */
    public static function getNextTokenGroupId($tokenable) {
        $id = $tokenable
            ->tokens()
            ->whereNotNull('group_id')
            ->orderByDesc('group_id')
            ->first('group_id');

        return $id !== null ? intval($id->group_id) + 1 : 1;
    }

    /**
     * Checks whether the abilities-object has all the abilities in the check-abilities array
     *
     * @param \TokenAuth\Contracts\HasAbilities $abilitiesObject
     * @param array $checkAbilities
     *
     * @throws \TokenAuth\Exceptions\MissingAbilityException If an ability is missing
     */
    protected static function checkHasAllAbilities(
        HasAbilities $abilitiesObject,
        array $checkAbilities
    ): void {
        if ($abilitiesObject->cant('*')) {
            foreach ($checkAbilities as $ability) {
                if (!$abilitiesObject->can($ability)) {
                    throw new MissingAbilityException(
                        $ability,
                        "Refresh token doesn't have and therefore can't give the ability: $ability"
                    );
                }
            }
        }
    }
}
