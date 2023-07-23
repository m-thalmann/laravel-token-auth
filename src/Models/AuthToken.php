<?php

namespace TokenAuth\Models;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Support\AuthTokenBuilder;

class AuthToken extends Model implements AuthTokenContract {
    use MassPrunable;

    protected $casts = [
        'type' => TokenType::class,
        'abilities' => 'array',
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    protected $attributes = [
        'group_id' => null,
        'name' => null,
        'abilities' => '[]',
        'expires_at' => null,
    ];

    public function authenticable() {
        return $this->morphTo('authenticable');
    }

    public function scopeActive(Builder $query) {
        $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
    }

    public function getType(): TokenType {
        return $this->type;
    }
    public function getAuthenticable(): Authenticatable {
        return $this->authenticable;
    }
    public function getGroupId(): ?int {
        return $this->group_id;
    }
    public function getName(): ?string {
        return $this->name;
    }
    public function getAbilities(): array {
        return $this->abilities;
    }
    public function hasAbility(string $ability): bool {
        return in_array('*', $this->abilities) ||
            in_array($ability, $this->abilities);
    }
    public function getExpiresAt(): ?CarbonInterface {
        return $this->expires_at;
    }
    public function isActive(): bool {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function setToken(string $plainTextToken): void {
        $this->token = self::hashToken($plainTextToken);
    }

    public function store(): void {
        $this->save();
    }

    public function remove(): void {
        $this->delete();
    }

    public function revoke(): static {
        $this->expires_at = now();
        return $this;
    }

    public function prunable() {
        return static::query()
            ->where('type', TokenType::ACCESS)
            ->orWhere(function ($query) {
                $removeBefore = now()->subHours(
                    config('tokenAuth.prune_after_hours')
                );

                $query->where('expires_at', '<=', $removeBefore);
            });
    }

    public static function find(
        ?TokenType $type,
        string $plainTextToken,
        bool $active = true
    ): ?static {
        return static::query()
            ->when(
                $type !== null,
                fn(Builder $query) => $query->where('type', $type)
            )
            ->where('token', self::hashToken($plainTextToken))
            ->when($active, fn(Builder $query) => $query->active())
            ->first();
    }

    public static function create(TokenType $type): AuthTokenBuilderContract {
        return (new AuthTokenBuilder(static::class))->setType($type);
    }

    public static function deleteTokensFromGroup(int $groupId): void {
        static::query()
            ->where('group_id', $groupId)
            ->delete();
    }

    private static function hashToken(string $plainTextToken): string {
        return hash('sha256', $plainTextToken);
    }
}
