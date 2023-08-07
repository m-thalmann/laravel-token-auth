<?php

return [
    /*
     * The amount of minutes after which a token expires
     */
    'expiration_minutes' => [
        'refresh' => env('TOKEN_AUTH_REFRESH_EXPIRATION', 60 * 24 * 7),
        'access' => env('TOKEN_AUTH_ACCESS_EXPIRATION', 10),
    ],

    /*
     * The amount of hours after which a revoked token is pruned
     */
    'prune_revoked_after_hours' => [
        'refresh' => env('TOKEN_AUTH_PRUNE_REVOKED_AFTER_HOURS', 24 * 7),
    ],

    /**
     * Whether or not to run the migrations
     */
    'run_migrations' => env('TOKEN_AUTH_RUN_MIGRATIONS', true),
];
