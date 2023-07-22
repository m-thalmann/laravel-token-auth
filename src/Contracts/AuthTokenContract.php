<?php

namespace TokenAuth\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use TokenAuth\Enums\TokenType;

interface AuthTokenContract {
    /**
     * Return the type of the token
     * @return TokenType
     */
    public function getType(): TokenType;

    /**
     * Return the tokenable instance associated with the token
     * @return Authenticatable
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
     * Return the expire date of the token
     * @return CarbonInterface|null
     */
    public function getExpiresAt(): ?CarbonInterface;

    /**
     * Return whether the token is active
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
     * @param TokenType|null $type The searched token type or null if any type
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
     * @param TokenType $type
     * @return AuthTokenBuilderContract
     */
    public static function create(TokenType $type): AuthTokenBuilderContract;

    /**
     * Delete all tokens from the given group
     * @param int $groupId
     */
    public static function deleteTokensFromGroup(int $groupId): void;
}
