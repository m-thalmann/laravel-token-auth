<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. If the value is null the token will never expire.
    |
    */

    'refresh_token_expiration' => 60 * 24 * 7, // 7 days
    'access_token_expiration' => 10,
];
