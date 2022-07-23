# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Creating tokens

You can create a token for a user by calling the `createToken()` method on the user model:

```php
$newAuthToken = $user->createToken(
  $tokenType, // one of TokenAuth::TYPE_ACCESS / TokenAuth::TYPE_REFRESH
  $tokenName, // the name of the token
  $tokenGroupId = null, // the group id of the token (see 'Token groups' below)
  $abilities = ['*'], // the abilities for this token (see abilities.md)
  $expiresInMinutes = null, // defines when the token expires (see 'Token expiration' below)
  $save = true // defines whether the token should be saved before returning
);
```

The return value is a `NewAuthToken` instance (see [below](#the-newauthtoken-class)).

If the `$save` argument is `false` you will have to manually save the token after it was created. This allows you to for example set additional properties before the model is being saved:

```php
$newToken = $user->createToken(..., save: false);

$token = $newToken->token;

$token->not_before = now()->addMinutes(10);
$token->save();

$plainTextToken = $newToken->plainTextToken;
```

> **Reminder:** Since PHP 8.0 you can use named arguments so you don't have to specify all of the arguments with default values if you don't need to change them: https://www.php.net/manual/en/functions.arguments.php#functions.named-arguments

### Helper methods

There are multiple helper methods in the `TokenAuth\TokenAuth` class to create tokens:

#### Creating access tokens

```php
// creates an access token for the authenticated user
TokenAuth::createAccessToken(
  $name,
  $abilities = ['*'],
  $expiresInMinutes = null,
  $save = true
);

TokenAuth::createAccessTokenForUser(
  $user, // the user model instance to create the token for
  $name,
  $abilities = ['*'],
  $expiresInMinutes = null,
  $save = true
);
```

#### Creating token pairs

```php
// creates a token pair for the authenticated user
TokenAuth::createTokenPair(
  $refreshTokenName,
  $accessTokenName,
  $tokenAbilities = [
    ['*'], // abilities for refresh token
    ['*'], // abilities for access token (must be subset of refresh token's abilities)
  ],
  $tokenExpirationMinutes = [
    null, // expiration time in minutes for refresh token
    null, // expiration time in minutes for access token
  ],
  $save = true
);

TokenAuth::createTokenPairForUser(
  $user, // the user model instance to create the tokens for
  $refreshTokenName,
  $accessTokenName,
  $tokenAbilities = [['*'], ['*']],
  $tokenExpirationMinutes = [null, null],
  $save = true
);
```

The return value of these methods is an array with the first element being the `NewAccessToken` instance for the refresh token and the second one being an instance for the access token:

```php
[$refreshToken, $accessToken] = TokenAuth::createTokenPair(...);

$refreshToken->plainTextToken;
$accessToken->plainTextToken;
```

#### Rotating refresh token

Here the refresh token is revoked (and directly saved if `$save` is `true`) and a new one (with the same name, group-id and abilities) is created. Additionally a new access token is created using the given arguments:

```php
// uses the authenticated user and its token (must be a refresh token)
TokenAuth::rotateRefreshToken(
  $accessTokenName,
  $accessTokenAbilities = ['*'], // must be subset of refresh token's abilities
  $tokenExpirationMinutes = [
    null, // expiration time in minutes for refresh token
    null, // expiration time in minutes for access token
  ],
  $save = true
);

TokenAuth::rotateRefreshTokenForUser(
  $user, // the user model instance to create the tokens for
  $refreshToken, // the refresh token used
  $accessTokenName,
  $accessTokenAbilities = ['*'],
  $tokenExpirationMinutes = [null, null],
  $save = true
);
```

The return value is again an array of `NewAccessToken` instances:

```php
[$refreshToken, $accessToken] = TokenAuth::rotateRefreshToken(...)
```

> **Information:** When using `rotateRefreshToken()` the refresh token is saved twice to the database (1. on authentication when the last_used timestamp is set; 2. after revoking). To improve this you could disable automatic saving of tokens on authentication. For more information on this see [configuration](./configuration.md#disable-auto-saving-of-token-on-authentication).

### Token groups

A token can belong to a token group, which is identified by an integer-id (not a foreign key or auto increment value). When creating new token pairs the group id is automatically set to the next available id by using the `TokenAuth::getNextTokenGroupId()`.

A token group identifies all tokens that belong together i.e. they have been created together / from refresh tokens from the same group. In case of a reuse detection all tokens from the same group can be deleted this way.

### Token expiration

A token can have a fixed expiration time after which the token can no longer be used for authentication. When creating a new token you may pass the time as minutes from now:

```php
TokenAuth::createAccessToken($tokenName, expiresInMinutes: 10);
```

If you do not set the value or pass `null`, the value from the configuration is used (`tokenAuth.token_expiration_minutes.<token type>`).

If you set the value to `-1` the token will never expire.

See [expiration](./expiration.md) for more information.

### The NewAuthToken class

The created token is always returned as an instance of the `TokenAuth\NewAuthToken` class:

```php
// the token model instance
$tokenInstance = $newAuthToken->token;

// the plaintext token to send to the client (no longer retrievable afterwards)
$plainTextToken = $newAuthToken->plainTextToken;
```

---

[Next: Abilities &rarr;](./abilities.md)
