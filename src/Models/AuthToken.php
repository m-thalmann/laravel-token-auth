<?php

namespace TokenAuth\Models;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use TokenAuth\Concerns\AuthTokenHelpers;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Support\AuthTokenBuilder;

class AuthToken extends Model implements AuthTokenContract {
    use AuthTokenHelpers, MassPrunable;

    protected $casts = [
        'type' => TokenType::class,
        'abilities' => 'array',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    protected $attributes = [
        'group_id' => null,
        'name' => null,
        'abilities' => '[]',
        'revoked_at' => null,
        'expires_at' => null,
    ];

    public function authenticatable(): MorphTo {
        return $this->morphTo();
    }

    public function scopeNotExpired(Builder $query): void {
        $query->where(function ($query) {
            $query->orWhere('expires_at', '>', now());
            $query->orWhereNull('expires_at');
        });
    }

    public function scopeNotRevoked(Builder $query): void {
        $query->whereNull('revoked_at');
    }

    public function scopeActive(Builder $query): void {
        $this->scopeNotExpired($query);
        $this->scopeNotRevoked($query);
    }

    public function scopeType(Builder $query, TokenType $type): void {
        $query->where('type', $type);
    }

    public function getType(): TokenType {
        return $this->type;
    }
    public function getAuthenticatable(): Authenticatable {
        return $this->authenticatable;
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
    public function getRevokedAt(): ?CarbonInterface {
        return $this->revoked_at;
    }
    public function getExpiresAt(): ?CarbonInterface {
        return $this->expires_at;
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
        $this->revoked_at = now();
        return $this;
    }

    public function prunable(): Builder {
        $typesRemovalTimes = collect(TokenType::cases())->map(
            fn(TokenType $type) => [
                'type' => $type,
                'removalTime' => now()->subHours(
                    config(
                        "tokenAuth.prune_revoked_after_hours.{$type->value}",
                        0
                    )
                ),
            ]
        );

        return static::query()
            ->where('expires_at', '<=', now())
            ->orWhere(function (Builder $query) use ($typesRemovalTimes) {
                foreach ($typesRemovalTimes as $typeRemovalTime) {
                    $query->orWhere(function (Builder $query) use (
                        $typeRemovalTime
                    ) {
                        $query->where('type', $typeRemovalTime['type']);
                        $query->where(
                            'revoked_at',
                            '<=',
                            $typeRemovalTime['removalTime']
                        );
                    });
                }
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

    public static function generateGroupId(
        Authenticatable $authenticatable
    ): int {
        return static::query()
            ->whereMorphedTo('authenticatable', $authenticatable)
            ->max('group_id') + 1;
    }

    public static function deleteTokensFromGroup(
        int $groupId,
        ?TokenType $type = null
    ): void {
        static::query()
            ->where('group_id', $groupId)
            ->when(
                $type !== null,
                fn(Builder $query) => $query->where('type', $type)
            )
            ->delete();
    }
}
