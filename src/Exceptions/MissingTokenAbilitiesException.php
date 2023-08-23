<?php

namespace TokenAuth\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

class MissingTokenAbilitiesException extends AuthorizationException {
    public function __construct(
        public readonly array $missingAbilities = [],
        ?string $message = null
    ) {
        if ($message === null) {
            $message = 'Token does not have certain abilities.';
        }

        parent::__construct($message);
    }
}
