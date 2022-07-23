# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Expiration

The tokens have predefined expiration times (defined in the config).

**Configuration:**

- `tokenAuth.token_expiration_minutes.refresh` - Default: _7 days_
- `tokenAuth.token_expiration_minutes.access` - Default: _10 minutes_

If you want to change the value for a single token you can do that this way (defined as minutes):

```php
TokenAuth::createTokenPair(
  $refreshTokenName,
  $accessTokenName,
  $pairAbilities,
  [
    60, // expiration time of refresh token
    60, // expiration time of access token
  ]
);

// the abilities stay the same for the refresh token
TokenAuth::rotateRefreshToken($accessTokenName, $accessTokenAbilities, [
  60, // expiration time of refresh token
  60, // expiration time of access token
]);

TokenAuth::createAccessToken(
  $accessTokenName,
  $accessTokenAbilities,
  60 // expiration time of access token
);
```

**Possible values:**

- `<number>`: minutes until expiration
- `null`: use value from config
- `-1`: token does not expire

### Pruning expired and/or revoked tokens

You might want to remove old expired and/or revoked tokens from the database. This can be done by calling / scheduling the Laravel command `model:prune`:

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule) {
  // ...
  $schedule->command('model:prune')->daily();
}
```

For this the model uses the `MassPrunable` trait and the `prunable` function (see [Laravel 9.x Docs](https://laravel.com/docs/9.x/eloquent#mass-pruning)). You can of course extend the / overwrite the `prunable` function for your own needs but keep in mind that the `deleting` / `deleted` events will not be dispatched for the model.

You can define after what amount of time an expired / revoked token will be pruned in the config-file:

- `tokenAuth.token_prune_after_hours.refresh` - Default: _2 weeks_
- `tokenAuth.token_prune_after_hours.access` - Default: _1 day_

---

[Next: Revoking tokens &rarr;](./revoking.md)
