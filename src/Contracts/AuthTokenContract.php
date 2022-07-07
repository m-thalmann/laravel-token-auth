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
     * Save the token model to the database.
     *
     * @see \Illuminate\Database\Eloquent\Model::save()
     *
     * @param array $options
     * @return bool
     */
    public function save(array $options = []);

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
