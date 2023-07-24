<?php

namespace TokenAuth\Enums;

enum TokenType: string {
    /**
     * An access token
     */
    case ACCESS = 'access';

    /**
     * A refresh token
     */
    case REFRESH = 'refresh';

    /**
     * A custom token type
     */
    case CUSTOM = 'custom';

    /**
     * Get the guard name for this token type
     * @return string
     */
    public function getGuardName(): string {
        return 'token-' . $this->value;
    }
}
