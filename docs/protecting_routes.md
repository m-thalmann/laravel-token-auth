# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Protecting Routes

To protect routes for requests you may use the `Authenticate` middleware provided by Laravel:

```php
Route::get('/private', function () {
  // ...
})->middleware('auth:<guard>');
```

Here the `<guard>` is one of the following:

- `token` - to allow any access token
- `token-refresh` - to allow any refresh token

After authentication the `auth()->user()` contains the used token:

```php
$authenticatedToken = auth()
  ->user()
  ->currentToken();
```

When a token is used for authentication it's `last_used` timestamp is automatically updated and the token is saved to the database. If you don't want the token to be saved on each request you can disable this behavior (see [configuration](./configuration.md)).

When a token is used for authentication the `TokenAuth\Events\TokenAuthenticated` event is triggered **before** the timestamp is set and saved. See [events](./events.md) for more information.

---

[Next: Events &rarr;](./events.md)
