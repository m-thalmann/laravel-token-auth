<?php

namespace TokenAuth\Support;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Enums\TokenType;
use TokenAuth\Models\AuthToken;
use Illuminate\Support\Str;

class AuthTokenBuilder implements AuthTokenBuilderContract {
    private AuthToken $instance;
    private ?string $plainTextToken = null;

    public function __construct(string $class) {
        $this->instance = new $class();
    }

    public function setType(TokenType $type): static {
        $this->instance->type = $type;
        return $this;
    }
    public function setAuthenticable(Authenticatable $authenticable): static {
        $this->instance->authenticable()->associate($authenticable);
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
        $this->instance->setToken($token);
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
    public function setExpiresAt(?CarbonInterface $expiresAt): static {
        $this->instance->expires_at = $expiresAt;
        return $this;
    }

    public function build(bool $save = true): NewAuthToken {
        if ($this->plainTextToken === null) {
            $this->setToken(Str::random(64));
        }

        if ($save) {
            $this->instance->store();
        }

        return new NewAuthToken($this->instance, $this->plainTextToken);
    }
}
