# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Configuration

### Table of contents

- [Custom Token Model](#custom-token-model)
- [Define how the token is retrieved from the request](#define-how-the-token-is-retrieved-from-the-request)
- [Define an additional validator on the token](#define-an-additional-validator-on-the-token)
- [Ignoring migrations](#ignoring-migrations)

### Custom Token Model

First define your custom model:

```php
use TokenAuth\Models\AuthToken as BaseAuthToken;

class AuthToken extends BaseAuthToken
{
  // ...
}
```

If you need to change the structure of the migration you have to publish (see the [installation section](#installation)) and modify it.

Then you have to register your custom model in one of the applications service providers (typically in the `AuthServiceProvider`):

```php
use App\Models\AuthToken;

public function boot(){
  // ...

  TokenAuth::useAuthTokenModel(AuthToken::class);
}
```

### Define how the token is retrieved from the request

Per default the token is retrieved by the `$request->bearerToken()` method.

If you need to have a different way to retrieve the token you can specify it like this (again define this in your `AuthServiceProvider`):

```php
public function boot(){
  // ...

  TokenAuth::getAuthTokenFromRequestUsing(function($request, $tokenType){
    if($tokenType === TokenAuth::TYPE_ACCESS) {
      return $request->header('X-Auth-Token');
    }else{
      return $request->header('X-Refresh-Token');
    }
  });
}
```

### Define an additional validator on the token

If you have to add a further validator to the token you can add one like this (again define this in your `AuthServiceProvider`):

```php
public function boot(){
  // ...

  TokenAuth::authenticateAuthTokensUsing(function($token, $isValid){
    if($token->custom_property) {
      return true;
    }

    return $isValid;
  });
}
```

### Ignoring migrations

If you do not want the migrations to be registered and run you can disable them by adding this to your `AuthServiceProvider`:

```php
public function boot(){
  // ...

  TokenAuth::ignoreMigrations();
}
```

---

[Next: Testing &rarr;](./testing.md)
