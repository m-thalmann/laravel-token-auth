# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Tokens

**See also:**

- [Creating tokens](./02-01-creating-tokens.md)
- [Token abilities](./02-02-token-abilities.md)

### Table of contents

- [`AuthTokenContract`](#authtokencontract)
- [`AuthToken`](#authtoken)
- [Transient tokens](#transient-tokens)

### `AuthTokenContract`

The tokens used for authentication must implement the `TokenAuth\Contracts\AuthTokenContract` interface. To define a custom model see the [Configuration](./04-configuration.md#customize-token-class) docs.

It defines the following functions:

```php
$token->getType(); // returns the token type
$token->getAuthenticatable(); // returns the authenticatable/user
$token->getGroupId(); // returns the group id
$token->getName(); // returns the token name

$token->getAbilities(); // returns the abilities
$token->hasAbility(); // checks whether the token has the given ability

$token->getRevokedAt(); // returns the revoked-at timestamp instance (CarbonInterface)
$token->isRevoked(); // returns whether the token is revoked
$token->getExpiresAt(); // returns the expires-at timestamp instance (CarbonInterface)
$token->isExpired(); // returns whether the token is expired
$token->isActive(); // returns whether the token is active (not revoked, not expired)

$token->setToken(string $plainTextToken); // sets the token to the instance (will be hashed)

$token->store(); // saves the token
$token->remove(); // deletes the token
$token->revoke(); // revokes the token (is not yet saved!)

AuthTokenContract::find(...); // searches for a token instance
AuthTokenContract::create(TokenType $type); // returns a new builder instance
AuthTokenContract::generateGroupId(Authenticatable $authenticatable); // returns a new group id for the given authenticatable
AuthTokenContract::deleteTokensFromGroup(...); // deletes all tokens from the given group
```

### `AuthToken`

The model provided by this package has the following additional methods/helpers:

```php
$token->authenticatable(); // the relationship to the authenticatable/user

$token->query()->notExpired(); // scope to only include non-expired tokens
$token->query()->notRevoked(); // scope to only include non-revoked tokens
$token->query()->active(); // scope to only include active tokens (not revoked, not expired)

$token->query()->type(TokenType::ACCESS); // scope to filter by type
```

### Transient tokens

If you ever need a token that is not stored anywhere and has the simple job to be used for one authentication only you can use the `TokenAuth\Support\TransientAuthToken` class. It has public members for all token-properties and basic implementations for every method required by the contract. Some of the methods throw an exception, since they would not make any sense (like the `find()` method for example).

**Example:**

```php
use TokenAuth\Enum\TokenType;
use TokenAuth\Support\TransientAuthToken;

$token = new TransientAuthToken();
$token->type = TokenType::ACCESS;
$token->authenticatable = auth()->user();

// do whatever you like with this token
```

> This could be used for example inside of the `getTokenInstance()` method of the `TokenGuard`, if you wanted to create some special tokens

---

[Next: Creating tokens &rarr;](./02-01-creating-tokens.md)
