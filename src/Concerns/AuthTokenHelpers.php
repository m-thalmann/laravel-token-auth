<?php

namespace TokenAuth\Concerns;

trait AuthTokenHelpers {
    public function hasAbility(string $ability): bool {
        return in_array('*', $this->getAbilities()) ||
            in_array($ability, $this->getAbilities());
    }

    public function isRevoked(): bool {
        $revokedAt = $this->getRevokedAt();
        return $revokedAt !== null && $revokedAt->isPast();
    }

    public function isExpired(): bool {
        $expiresAt = $this->getExpiresAt();
        return $expiresAt !== null && $expiresAt->isPast();
    }

    public function isActive(): bool {
        return !$this->isRevoked() && !$this->isExpired();
    }

    /**
     * Hash the given token
     * @param string $plainTextToken
     * @return string
     */
    protected static function hashToken(string $plainTextToken): string {
        return hash('sha256', $plainTextToken);
    }
}
