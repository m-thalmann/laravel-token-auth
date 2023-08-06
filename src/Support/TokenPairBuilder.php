<?php

namespace TokenAuth\Support;

use Carbon\CarbonInterface;
use Closure;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use LogicException;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Enums\TokenType;

class TokenPairBuilder implements AuthTokenBuilderContract {
    protected ?Closure $beforeBuildCallback = null;

    public function __construct(
        public readonly AuthTokenBuilderContract $accessToken,
        public readonly AuthTokenBuilderContract $refreshToken,
        protected readonly bool $mustSave = false
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
    public function getAbilities(): array {
        throw new LogicException('Can\'t get abilities on a token pair');
    }
    public function setExpiresAt(?CarbonInterface $expiresAt): static {
        $this->accessToken->setExpiresAt($expiresAt);
        $this->refreshToken->setExpiresAt($expiresAt);
        return $this;
    }

    /**
     * Set a callback to be called before the tokens are built (within the transaction)
     * @param Closure $callback
     */
    public function beforeBuild(Closure $callback): void {
        $this->beforeBuildCallback = $callback;
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
        if ($save) {
            DB::beginTransaction();
        }

        if (!$save && $this->mustSave) {
            throw new LogicException('This pair must be saved when built');
        }

        try {
            $this->checkAbilitiesAreEqual();

            if (is_callable($this->beforeBuildCallback)) {
                call_user_func($this->beforeBuildCallback);
            }

            $accessToken = $this->accessToken->build($save);
            $refreshToken = $this->refreshToken->build($save);

            if ($save) {
                DB::commit();
            }

            return new NewAuthTokenPair($accessToken, $refreshToken);
        } catch (Exception $e) {
            if ($save) {
                DB::rollBack();
            }

            throw $e;
        }
    }

    private function checkAbilitiesAreEqual(): void {
        $accessAbilities = $this->accessToken->getAbilities();
        $refreshAbilities = $this->refreshToken->getAbilities();

        $abilitiesAreEqual =
            count($accessAbilities) == count($refreshAbilities) &&
            array_diff($accessAbilities, $refreshAbilities) ===
                array_diff($refreshAbilities, $accessAbilities);

        if ($abilitiesAreEqual) {
            return;
        }

        throw new LogicException(
            'Access and refresh tokens must have the same abilities'
        );
    }
}
