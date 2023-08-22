# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Creating tokens

### Table of contents

- [AuthTokenBuilder](#authtokenbuilder)
- [Standalone tokens](#standalone-tokens)
- [Token pairs](#token-pairs)
  - [Rotating token pairs](#rotating-token-pairs)

### AuthTokenBuilder

When creating new tokens an instance of the `AuthTokenBuilder` class is used (see below how you can create an instance). This class has the following methods to set the properties of the token:

- `setType(TokenType $type)`
- `setAuthenticatable(Authenticatable $user)`
- `setGroupId(?int $groupId)`
- `setName(?string $name)`
- `setToken(string $token)`
- `setAbilities(string ...$abilities)`
- `addAbilities(string ...$abilities)`
- `setExpiresAt(?CarbonInterface $expiresAt)`

> These methods are chainable. E.g. `$builder->setGroupId(1)->setName('myName');`

The token instance can then be creating using the `build(bool $save = true)` method, which returns a `NewAuthToken` (containing the plain-text-token and the token instance).

> The builder automatically generates a plain-text-token if no token was set.
> It also sets the `expiresAt` value to the configured value if not otherwise set.

**Example:**

```php
use TokenAuth\Enums\TokenType;

$user = auth()->user();

$newAuthToken = $builder
  ->setType(TokenType::ACCESS)
  ->setAuthenticatable($user)
  ->setAbilities('user:create', 'user:delete')
  ->build(save: true); // passing `save: true` is optional

$token = $newAuthToken->token;
$plainTextToken = $newAuthToken->plainTextToken;
```

> If you want to set properties to the token before it is being saved, you have to pass the `save: false` argument to the `build()` method. You then have to save the token yourself.

### Standalone tokens

Creating a standalone token can be achieved by calling the static `create(TokenType $tokenType)` method on the used token model:

```php
use TokenAuth\Models\AuthToken;
use TokenAuth\Enums\TokenType;

$builder = AuthToken::create(TokenType::ACCESS);
```

> If you created your own AuthToken, you have to call the `create` method on that class!

Afterwards you can simply use the methods provided by the builder to create the instance (see above).

### Token pairs

To create token pairs you can use the `TokenAuth::createTokenPair()` method. It returns a `AuthTokenPairBuilder`, which has the same methods as the `AuthTokenBuilder` with some exceptions. It will set the properties onto both of the tokens and build the pair using the `buildPair()` method.

**Differences to the `AuthTokenBuilder`**:

- The following methods are not allowed: `setType`, `setToken`, `build`
- The `buildPair` method is used to build the pair. It returns a `NewAuthTokenPair` instance
- Since the `buildPair` method always saves the tokens inside of a transaction, you can add callbacks that are executed before saving (but after building) using the `beforeBuildSave` method
- The static `fromToken` method creates a new pair-builder instance, where the tokens get the properties from the given token (except the `revokedAt` and `expiresAt` properties)

**Example:**

```php
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Support\NewAuthTokenPair;

$user = auth()->user();

$newTokenPair = TokenAuth::createTokenPair($user)
  ->setAbilities('user:create')
  ->beforeBuildSave(function (NewAuthTokenPair $pair) {
    // ... do something with the tokens
  })
  ->buildPair();

$newAccessToken = $newTokenPair->accessToken;
$newRefreshToken = $newTokenPair->refreshToken;

$accessToken = $newAccessToken->token;
$accessPlainTextToken = $newAccessToken->plainTextToken;

// ...
```

> If you do not want the `groupId` to be generated and set automatically, you can pass the `generateGroupId: false` argument to the `createTokenPair` method.

#### Rotating token pairs

When using a refresh token you most of the time want to rotate the token pair, as described in the [README](../README.md#refresh-tokens). For this the `TokenAuth::rotateTokenPair()` method can be used. It returns a new `AuthTokenPairBuilder` with the tokens having the same properties as the given token (see `fromToken` above).

**Example:**

```php
use TokenAuth\Facades\TokenAuth;

$usedRefreshToken = TokenAuth::currentToken();

$newTokenPair = TokenAuth::rotateTokenPair($usedRefreshToken)->buildPair();
```

The provided refresh token will be revoked and all previous access tokens from the same group will be deleted.

> You can prevent deleting the previous access tokens by passing the `deleteAccessTokens: false` argument

---

[Next: Token abilities &rarr;](./02-02-token-abilities.md)
