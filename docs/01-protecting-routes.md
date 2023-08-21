# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Protecting routes

To protect routes for requests you may use the `Authenticate` middleware provided by Laravel:

```php
Route::get('/private', function () {
  // ...
})->middleware('auth:<guard>');
```

Here the `<guard>` is one of the following:

- `token-access` - to allow any access token
- `token-refresh` - to allow any refresh token
- `token-custom` - to allow any custom token

> If you have set a default guard as described in [here](./README.md#configuring-the-default-guard-optional), you can omit setting the `<guard>`-value for that guard.

After authentication the used token can be retrieved using:

```php
use TokenAuth\Facades\TokenAuth;

$authenticatedToken = TokenAuth::currentToken();
```

When a token is used for authentication the `TokenAuth\Events\TokenAuthenticated` event is triggered. See [events](./03-events.md) for more information.

### Alternative approach

You can also set the used guard in the following manner, by using the `TokenType` enum:

```php
Route::get('/private', function () {
  // ...
})->middleware('auth:' . TokenType::ACCESS->getGuardName());
```

---

[Next: Creating tokens &rarr;](./02-creating-tokens.md)
