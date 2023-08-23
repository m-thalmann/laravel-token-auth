<?php

namespace TokenAuth\Support;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Models\AuthToken;
use Illuminate\Support\Str;

class AuthTokenBuilder implements AuthTokenBuilderContract {
    protected ?string $plainTextToken = null;
    protected bool $expiresAtSet = false;

    public function __construct(protected readonly AuthToken $instance) {
    }

    public function setType(TokenType $type): static {
        $this->instance->type = $type;
        return $this;
    }
    public function setAuthenticatable(
        Authenticatable $authenticatable
    ): static {
        $this->instance->authenticatable()->associate($authenticatable);
        return $this;
    }
    public function setGroupId(?int $groupId): static {
        $this->instance->group_id = $groupId;
        return $this;
    }
    public function setName(?string $name): static {
        $this->instance->name = $name;
        return $this;
    }
    public function setToken(string $token): static {
        $this->plainTextToken = $token;
        $this->instance->token = $this->instance::hashToken($token);
        return $this;
    }
    public function setAbilities(string ...$abilities): static {
        $this->instance->abilities = $abilities;
        return $this;
    }
    public function addAbilities(string ...$abilities): static {
        $this->instance->abilities = array_merge(
            $this->instance->abilities,
            $abilities
        );
        return $this;
    }
    public function getAbilities(): array {
        return $this->instance->abilities;
    }
    public function setExpiresAt(?CarbonInterface $expiresAt): static {
        $this->instance->expires_at = $expiresAt;
        $this->expiresAtSet = true;
        return $this;
    }

    protected function useConfiguredExpiration(): void {
        $expirationMinutes = config(
            "tokenAuth.expiration_minutes.{$this->instance->getType()->value}",
            null
        );

        $expiresAt =
            $expirationMinutes !== null
                ? now()->addMinutes($expirationMinutes)
                : null;

        $this->setExpiresAt($expiresAt);
    }

    public function build(bool $save = true): NewAuthToken {
        if ($this->plainTextToken === null) {
            $this->setToken(Str::random(64));
        }
        if (!$this->expiresAtSet) {
            $this->useConfiguredExpiration();
        }

        if ($save) {
            $this->instance->store();
        }

        return new NewAuthToken($this->instance, $this->plainTextToken);
    }
}
