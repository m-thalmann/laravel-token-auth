# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Testing

For authenticating users for a request you can use the `TokenAuth::actingAs()` method:

```php
TokenAuth::actingAs($user, $abilities = [], $guard = 'token');
```

It returns the mocked token instance with the set abilities. The type of the token depends on the set guard (see below).
If you want to retrieve the type from the mocked token you have to use the `$token->getType()`, since Mockery can't mock Eloquent attributes.
Likewise if you have any custom attributes defined you may need to define getters for them an extend the mocked object.

The guard has to be one of:

- `token` - authenticated using an access token
- `token-refresh` - authenticated using a refresh token

If you pass `null` for the `$user` the user is logged out and `null` is returned. This uses the `app('auth')->forgetGuards()` method.

### Example usage

```php
public function testGetAuthenticatedUserInformation(){
    $user = User::factory()->create();

    $this->get('/auth/info')->assertUnauthorized();

    TokenAuth::actingAs($user);

    $response = $this->get('/auth/info');

    $response->assertOk();
    $response->assertJson($user->toArray());
}
```
