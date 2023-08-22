# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Token abilities

When creating a token you can give it a set of abilities. These abilities are self defined strings, with the exception of the `*` (see below).

> The `*` ability is the only special case: it includes all possible abilities i.e. it is a wildcard. If a token has this ability it will be able to do anything.

**Example:**

```php
use TokenAuth\Enums\TokenType;
use TokenAuth\Models\AuthToken;

$user = auth()->user();

$newToken = AuthToken::create(TokenType::ACCESS)
  ->setAuthenticatable($user)
  ->setAbilities('ability1', 'ability2', 'ability3')
  ->build();

$accessToken = $newToken->token;

dump($accessToken->hasAbility('ability1')); // true
dump($accessToken->hasAbility('ability5')); // false
```

When defining a route you can also set the middleware used to check for certain abilities:

```php
Route::post('/article', function ($request) {
  // token has either 'admin' or 'create-article' ability
})->middleware(['auth:token-access', 'abilities:admin,create-article']);

Route::post('/user', function ($request) {
  // token has both the 'admin' and 'create-user' ability
})->middleware(['auth:token-access', 'abilities:admin,create-article']);
```

> You first have to setup the middleware as described [here](./README.md#adding-the-middleware-optional)

---

[Next: Events &rarr;](./03-events.md)
