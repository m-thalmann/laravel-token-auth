<?php

namespace TokenAuth\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;

class MissingAbilityException extends AuthorizationException {
    /**
     * The abilities that the user did not have.
     *
     * @var array
     */
    protected $abilities;

    /**
     * Create a new missing scope exception.
     *
     * @param array|string $abilities
     * @param string|null $message If no message is passed the translation of `tokenAuth::errors.missing_abilities` is used
     * @return void
     */
    public function __construct($abilities = [], $message = null) {
        if ($message === null) {
            $message = __('tokenAuth::errors.missing_abilities');
        }

        parent::__construct($message);

        $this->abilities = Arr::wrap($abilities);
    }

    /**
     * Get the abilities that the user did not have.
     *
     * @return array
     */
    public function abilities() {
        return $this->abilities;
    }
}

