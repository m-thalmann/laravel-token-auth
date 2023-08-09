<?php

namespace TokenAuth\Support;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use LogicException;
use TokenAuth\Contracts\AuthTokenBuilderContract;
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Enums\TokenType;

class AuthTokenPairBuilder implements AuthTokenBuilderContract {
    protected array $beforeBuildSaveCallbacks = [];

    public function __construct(
        public readonly AuthTokenBuilderContract $accessToken,
        public readonly AuthTokenBuilderContract $refreshToken
    ) {
    }

    public function setType(TokenType $type): static {
        throw new LogicException('Can\'t set type on a token pair');
    }
    public function setAuthenticatable(
        Authenticatable $authenticatable
    ): static {
        $this->accessToken->setAuthenticatable($authenticatable);
        $this->refreshToken->setAuthenticatable($authenticatable);
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

    public function build(bool $save = true): NewAuthToken {
        throw new LogicException('Use the `buildPair` method instead');
    }

    /**
     * Set a callback to be called before saving the tokens (within a transaction).
     * The function receives the NewAuthTokenPair instance as its only argument (not yet saved).
     * @param \Closure $callback
     * @return static
     */
    public function beforeBuildSave(Closure $callback): static {
        $this->beforeBuildSaveCallbacks[] = $callback;
        return $this;
    }

    /**
     * Build the token instances and return them in the form of a NewAuthTokenPair.
     * Both tokens are built and saved within a transaction. Before saving, the callbacks set with `beforeBuildSave` are called (also within the transaction).
     * @return \TokenAuth\Support\NewAuthTokenPair
     */
    public function buildPair(): NewAuthTokenPair {
        $this->checkAbilitiesAreEqual();

        return DB::transaction(function () {
            $accessToken = $this->accessToken->build(save: false);
            $refreshToken = $this->refreshToken->build(save: false);

            $tokenPair = new NewAuthTokenPair($accessToken, $refreshToken);

            foreach ($this->beforeBuildSaveCallbacks as $callback) {
                if (is_callable($callback)) {
                    call_user_func_array($callback, [$tokenPair]);
                }
            }

            $accessToken->token->store();
            $refreshToken->token->store();

            return $tokenPair;
        });
    }

    /**
     * Checks if the access and refresh tokens have the same abilities.
     */
    protected function checkAbilitiesAreEqual(): void {
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

    /**
     * Creates a new AuthTokenPairBuilder from an existing token
     * by creating new tokens with the same properties as the given token.
     * @param \TokenAuth\Contracts\AuthTokenContract $token
     * @return \TokenAuth\Support\AuthTokenPairBuilder
     */
    public static function fromToken(AuthTokenContract $token) {
        $accessToken = $token::create(TokenType::ACCESS);
        $refreshToken = $token::create(TokenType::REFRESH);

        $builder = new static($accessToken, $refreshToken);

        $builder->setAuthenticatable($token->getAuthenticatable());
        $builder->setGroupId($token->getGroupId());
        $builder->setName($token->getName());
        $builder->setAbilities(...$token->getAbilities());

        return $builder;
    }
}
