<?php

return [
    /*
     * The amount of minutes after which a token expires
     */
    'expiration_minutes' => [
        'refresh' => 60 * 24 * 7,
        'access' => 10,
    ],

    /*
     * The amount of hours after which a revoked token is pruned
     */
    'prune_revoked_after_hours' => [
        'refresh' => 24 * 7,
    ],

    /**
     * Whether or not to run the migrations
     */
    'run_migrations' => true,
];
