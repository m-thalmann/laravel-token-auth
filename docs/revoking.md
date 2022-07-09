# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Revoking

A token can be revoked, meaning it can no longer be used and will trigger a `RevokedTokenReused` event if it is used again.

> **Note:** Revoking is not the same as deleting a token! A revoked token is still kept in the database and triggers a `RevokedTokenReused` event and deletes all tokens from the same group if it is reused. If on the other hand a deleted token is used the user can simply not be authenticated, since the token can not be found in the database.

You can revoke a token by calling the `revoke()` method on the token and saving it afterwards (the method does only set the token as revoked and doesn't save the state to the database):

```php
$token->revoke()->save();
```

Revoked tokens will still appear in the relationship of the user. If you do not want to include them you can apply a filter:

```php
// Example for the default AuthToken model
$activeTokens = $user
  ->tokens()
  ->whereNull('revoked_at')
  ->get();
```

**Note:** In this example the expired tokens are still contained in the result so you would need to filter them additionally.

---

[Next: Protecting routes &rarr;](./protecting_routes.md)
