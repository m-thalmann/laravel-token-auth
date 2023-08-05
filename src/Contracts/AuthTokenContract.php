<?php

namespace TokenAuth\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use TokenAuth\Enums\TokenType;

interface AuthTokenContract {
    /**
     * Return the type of the token
     * @return \TokenAuth\Enums\TokenType
     */
    public function getType(): TokenType;

    /**
     * Return the tokenable instance associated with the token
     * @return \Illuminate\Contracts\Auth\Authenticatable
     */
    public function getAuthenticable(): Authenticatable;

    /**
     * Return the group id of the token
     * @return int|null
     */
    public function getGroupId(): ?int;

    /**
     * Return the name of the token
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Return the list of abilities the token has
     * @return string[]
     */
    public function getAbilities(): array;

    /**
     * Return whether the token has the given ability
     * @param string $ability
     * @return bool
     */
    public function hasAbility(string $ability): bool;

    /**
     * Return the revoked date of the token
     * @return \Carbon\CarbonInterface|null
     */
    public function getRevokedAt(): ?CarbonInterface;

    /**
     * Return whether the token is revoked
     * @return bool
     */
    public function isRevoked(): bool;

    /**
     * Return the expire date of the token
     * @return \Carbon\CarbonInterface|null
     */
    public function getExpiresAt(): ?CarbonInterface;

    /**
     * Return whether the token is expired
     * @return bool
     */
    public function isExpired(): bool;

    /**
     * Return whether the token is active (not revoked and not expired)
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Set the token of the token instance.
     * Should hash the plain text token before storing it.
     * @param string $plainTextToken
     */
    public function setToken(string $plainTextToken): void;

    /**
     * Save the token instance
     */
    public function store(): void;

    /**
     * Delete the token instance
     */
    public function remove(): void;

    /**
     * Revoke the token instance.
     * Does *not* store the update.
     * @return $this
     */
    public function revoke(): static;

    /**
     * Find the token instance matching the given type and token
     *
     * @param \TokenAuth\Enums\TokenType|null $type The searched token type or null if any type
     * @param string $plainTextToken
     * @param bool $active
     *
     * @return static|null
     */
    public static function find(
        ?TokenType $type,
        string $plainTextToken,
        bool $active = true
    ): ?static;

    /**
     * Create an AuthTokenBuilder instance and return it
     * @param \TokenAuth\Enums\TokenType $type
     * @return \TokenAuth\Contracts\AuthTokenBuilderContract
     */
    public static function create(TokenType $type): AuthTokenBuilderContract;

    /**
     * Generate a group id for the given authenticable
     * @param \Illuminate\Contracts\Auth\Authenticatable $authenticatable
     * @return int
     */
    public static function generateGroupId(
        Authenticatable $authenticatable
    ): int;

    /**
     * Delete all tokens from the given group with the given type (or all if is set to null)
     * @param int $groupId
     * @param \TokenAuth\Enums\TokenType|null $type
     */
    public static function deleteTokensFromGroup(
        int $groupId,
        ?TokenType $type = null
    ): void;
}
