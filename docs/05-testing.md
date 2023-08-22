# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Testing

For authenticating users for a request you can use the `TokenAuth::actingAs()` method:

```php
use TokenAuth\Enums\TokenType;
use TokenAuth\Facades\TokenAuth;

TokenAuth::actingAs(
    ?Authenticatable $user,
    array $abilities = [],
    TokenType $tokenType = TokenType::ACCESS
);
```

It returns the mocked token instance with the set type and abilities.

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
