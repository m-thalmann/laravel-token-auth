<?php

namespace TokenAuth\Support;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Enums\TokenType;

class TokenPairBuilder implements AuthTokenBuilderContract {
    public function __construct(
        public readonly AuthTokenBuilderContract $accessToken,
        public readonly AuthTokenBuilderContract $refreshToken
    ) {
    }

    public function setType(TokenType $type): static {
        throw new LogicException('Can\'t set type on a token pair');
    }
    public function setAuthenticable(Authenticatable $authenticable): static {
        $this->accessToken->setAuthenticable($authenticable);
        $this->refreshToken->setAuthenticable($authenticable);
        return $this;
    }
    public function setGroupId(?int $groupId): static {
        $this->accessToken->setGroupId($groupId);
        $this->refreshToken->setGroupId($groupId);
        return $this;
    }
    public function setName(?string $name): static {
        $this->accessToken->setName($name);
        $this->refreshToken->setName($name);
        return $this;
    }
    public function setToken(string $token): static {
        throw new LogicException('Can\'t set token on a token pair');
    }
    public function setAbilities(string ...$abilities): static {
        $this->accessToken->setAbilities(...$abilities);
        $this->refreshToken->setAbilities(...$abilities);
        return $this;
    }
    public function addAbilities(string ...$abilities): static {
        $this->accessToken->addAbilities(...$abilities);
        $this->refreshToken->addAbilities(...$abilities);
        return $this;
    }
    public function setExpiresAt(?CarbonInterface $expiresAt): static {
        $this->accessToken->setExpiresAt($expiresAt);
        $this->refreshToken->setExpiresAt($expiresAt);
        return $this;
    }

    public function build(bool $save = true): NewAuthToken {
        throw new LogicException('Use the `buildPair` method instead');
    }

    /**
     * Build the token instances and return them in the form of a NewAuthTokenPair
     * @param bool $save
     * @return \TokenAuth\Support\NewAuthTokenPair
     */
    public function buildPair(bool $save = true): NewAuthTokenPair {
        $accessToken = $this->accessToken->build($save);
        $refreshToken = $this->refreshToken->build($save);

        return new NewAuthTokenPair($accessToken, $refreshToken);
    }
}
