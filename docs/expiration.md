# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Expiration

The tokens have predefined expiration times (defined in the config).

**Configuration:**

- `tokenAuth.refresh_token_expiration` - Default: _7 days_
- `tokenAuth.access_token_expiration` - Default: _10 minutes_

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

---

[Next: Revoking tokens &rarr;](./revoking.md)
