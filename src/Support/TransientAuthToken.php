<?php

namespace TokenAuth\Support;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use TokenAuth\Concerns\AuthTokenHelpers;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;

class TransientAuthToken implements AuthTokenContract {
    use AuthTokenHelpers;

    public TokenType $type;
    public string $token;
    public Authenticatable $authenticatable;
    public ?int $groupId = null;
    public ?string $name = null;
    public array $abilities = [];
    public ?CarbonInterface $expiresAt = null;

    public function getType(): TokenType {
        return $this->type;
    }
    public function getAuthenticatable(): Authenticatable {
        return $this->authenticatable;
    }
    public function getGroupId(): ?int {
        return $this->groupId;
    }
    public function getName(): ?string {
        return $this->name;
    }
    public function getAbilities(): array {
        return $this->abilities;
    }
    public function getRevokedAt(): ?CarbonInterface {
        return null;
    }
    public function getExpiresAt(): ?CarbonInterface {
        return $this->expiresAt;
    }

    public function store(): void {
        throw new LogicException(
            'The "store" method is not implemented for transient tokens'
        );
    }

    public function remove(): void {
        throw new LogicException(
            'The "remove" method is not implemented for transient tokens'
        );
    }

    public function revoke(): static {
        throw new LogicException(
            'The "revoke" method is not implemented for transient tokens'
        );
    }

    public function toArray(): array {
        return [
            'type' => $this->type,
            'authenticatable' => $this->authenticatable,
            'group_id' => $this->groupId,
            'name' => $this->name,
            'abilities' => $this->abilities,
            'expires_at' => $this->expiresAt,
        ];
    }

    public static function find(
        ?TokenType $type,
        string $plainTextToken,
        bool $mustBeActive = true
    ): ?static {
        throw new LogicException(
            'The "find" method is not implemented for transient tokens'
        );
    }

    public static function create(TokenType $type): AuthTokenBuilderContract {
        throw new LogicException(
            'The "create" method is not implemented for transient tokens'
        );
    }

    public static function generateGroupId(
        Authenticatable $authenticatable
    ): int {
        throw new LogicException(
            'The "generateGroupId" method is not implemented for transient tokens'
        );
    }

    public static function deleteTokensFromGroup(
        int $groupId,
        ?TokenType $type = null
    ): void {
        throw new LogicException(
            'The "deleteTokensFromGroup" method is not implemented for transient tokens'
        );
    }
}
