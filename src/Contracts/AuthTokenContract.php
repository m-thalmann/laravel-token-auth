<?php

namespace TokenAuth\Contracts;

interface AuthTokenContract extends HasAbilities {
    /**
     * Delete all tokens from the same group (if group is set).
     * This occurs for example if a reuse is detected
     */
    public function deleteAllTokensFromSameGroup();

    /**
     * Set the token as revoked (not saved to the database)
     *
     * @return $this
     */
    public function revoke();

    /**
     * Return whether the token is revoked
     *
     * @return boolean
     */
    public function isRevoked();

    /**
     * Return the type of the token (refresh / access)
     *
     * @return string
     */
    public function getType();

    /**
     * Find the access token instance matching the given token.
     *
     * @param string $token
     * @return static|null
     */
    public static function findAccessToken($token);

    /**
     * Find the refresh token instance matching the given token.
     *
     * @param string $token
     *
     * @return static|null
     */
    public static function findRefreshToken($token);
}
