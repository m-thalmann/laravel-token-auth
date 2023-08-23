<?php

namespace TokenAuth\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use TokenAuth\Enums\TokenType;
use TokenAuth\Support\NewAuthToken;

interface AuthTokenBuilderContract {
    /**
     * Set the type of the token
     * @param \TokenAuth\Enums\TokenType $type
     * @return static
     */
    public function setType(TokenType $type): static;
    /**
     * Set the tokenable instance associated with the token
     * @param \Illuminate\Contracts\Auth\Authenticatable $authenticatable
     * @return static
     */
    public function setAuthenticatable(
        Authenticatable $authenticatable
    ): static;
    /**
     * Set the group id of the token
     * @param int|null $groupId
     * @return static
     */
    public function setGroupId(?int $groupId): static;
    /**
     * Set the name of the token
     * @param string|null $name
     * @return static
     */
    public function setName(?string $name): static;
    /**
     * Set the plain-text token.
     * Should hash the plain text token before setting it.
     * @param string $token
     * @return static
     */
    public function setToken(string $token): static;
    /**
     * Set the list of abilities the token has
     * @param string ...$abilities
     * @return static
     */
    public function setAbilities(string ...$abilities): static;
    /**
     * Add abilities to the list of abilities the token has
     * @param string ...$abilities
     * @return static
     */
    public function addAbilities(string ...$abilities): static;
    /**
     * Return the set abilities
     * @return string[]
     */
    public function getAbilities(): array;
    /**
     * Set the expire date of the token
     * @param \Carbon\CarbonInterface|null $expiresAt
     * @return static
     */
    public function setExpiresAt(?CarbonInterface $expiresAt): static;

    /**
     * Build the token instance and return it in the form of a NewAuthToken
     * containing the token instance and the plain-text token
     * @param bool $save
     * @return \TokenAuth\Support\NewAuthToken
     */
    public function build(bool $save = true): NewAuthToken;
}
