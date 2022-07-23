<?php

namespace TokenAuth\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\TokenAuth;

class AuthToken extends Model implements AuthTokenContract {
    use MassPrunable;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'abilities' => 'json',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'group_id',
        'name',
        'token',
        'abilities',
        'expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = ['token'];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'group_id' => null,
        'abilities' => null,
        'revoked_at' => null,
        'expires_at' => null,
    ];

    /**
     * Get the tokenable model that the access token belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function tokenable() {
        return $this->morphTo('tokenable');
    }

    /**
     * Deletes all tokens from the same group (if group is set).
     * This occurs for example if a reuse is detected
     */
    public function deleteAllTokensFromSameGroup() {
        $query = static::query()->where('id', $this->id);

        if ($this->group_id !== null) {
            $query = $query->orWhere('group_id', $this->group_id);
        }

        $query->delete();
    }

    /**
     * Set the token as revoked (not saved to the database)
     *
     * @return $this
     */
    public function revoke() {
        $this->forceFill(['revoked_at' => now()]);

        return $this;
    }

    /**
     * Return whether the token is revoked
     *
     * @return boolean
     */
    public function isRevoked() {
        return $this->revoked_at !== null;
    }

    /**
     * Determine if the token has a given ability.
     *
     * @param string $ability
     * @return bool
     */
    public function can($ability) {
        return in_array('*', $this->abilities) ||
            in_array($ability, $this->abilities);
    }

    /**
     * Determine if the token is missing a given ability.
     *
     * @param string $ability
     * @return bool
     */
    public function cant($ability) {
        return !$this->can($ability);
    }

    /**
     * Return the type of the token (refresh / access)
     *
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Find the access token instance matching the given token.
     *
     * @param string $token
     * @return static|null
     */
    public static function findAccessToken($token) {
        return self::findToken(TokenAuth::TYPE_ACCESS, $token);
    }

    /**
     * Find the refresh token instance matching the given token.
     *
     * @param string $token
     *
     * @return static|null
     */
    public static function findRefreshToken($token) {
        return self::findToken(TokenAuth::TYPE_REFRESH, $token);
    }

    /**
     * Find the token instance with the given type matching the given token.
     *
     * @param string $type
     * @param string $token
     *
     * @return static|null
     */
    private static function findToken($type, $token) {
        return static::where('type', $type)
            ->where('token', hash('sha256', $token))
            ->first();
    }

    public function prunable() {
        return static::where(function ($query) {
            $query->where('type', TokenAuth::TYPE_ACCESS);
            $query->where(function ($query) {
                $removeBefore = now()->subHours(
                    config('tokenAuth.token_prune_after_hours.access')
                );

                $query->where('expires_at', '<=', $removeBefore);
                $query->orWhere('revoked_at', '<=', $removeBefore);
            });
        })->orWhere(function ($query) {
            $query->where('type', TokenAuth::TYPE_REFRESH);
            $query->where(function ($query) {
                $removeBefore = now()->subHours(
                    config('tokenAuth.token_prune_after_hours.refresh')
                );

                $query->where('expires_at', '<=', $removeBefore);
                $query->orWhere('revoked_at', '<=', $removeBefore);
            });
        });
    }
}
