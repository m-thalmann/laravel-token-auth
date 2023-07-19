<?php

namespace TokenAuth\Enums;

enum TokenType: string {
    /**
     * A standalone access token
     */
    case ACCESS = 'access';
    /**
     * A token used as a refresh & access token-pair
     */
    case PAIR = 'pair';

    /**
     * A custom token type
     */
    case CUSTOM = 'custom';
}
