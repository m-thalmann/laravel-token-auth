<?php

namespace TokenAuth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use TokenAuth\Enums\TokenType;

class AuthToken extends Model {
    use MassPrunable;

    protected $casts = [
        'type' => TokenType::class,
        'abilities' => 'json',
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    protected $attributes = [
        'group_id' => null,
        'name' => null,
        'abilities' => null,
        'expires_at' => null,
    ];

    public function tokenable() {
        return $this->morphTo('tokenable');
    }

    public function scopeActive(Builder $query) {
        $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
    }

    public static function scopeToken(
        Builder $query,
        TokenType $type,
        string $token
    ) {
        $query
            ->where('type', $type)
            ->active()
            ->where('token', $token);
    }

    public function prunable() {
        return static::where('expires_at', '<=', now());
    }
}
