# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Configuration

### Table of contents

- [Custom Token Model](#custom-token-model)
- [Define how the token is retrieved from the request](#define-how-the-token-is-retrieved-from-the-request)
- [Define an additional validator on the token](#define-an-additional-validator-on-the-token)
- [Ignoring migrations](#ignoring-migrations)
- [Disable auto-saving of token on authentication](#disable-auto-saving-of-token-on-authentication)

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

### Disable auto-saving of token on authentication

When a token is used for authentication it's `last_used` timestamp is automatically updated and the token is saved to the database. If you do not want the token to be automatically saved (or saved at all), you can disable this (as you might have guessed, again in the `AuthServiceProvider`):

```php
public function boot(){
  // ...

  TokenAuth::dontSaveTokenOnAuthentication();
}
```

But be aware that you have to manually save the token if you want the updated timestamp to be persisted:

```php
Route::get('/private', function () {
  auth()
    ->user()
    ->currentToken()
    ->save();
})->middleware('auth:token');
```

To handle this you can also use the `SaveAuthToken` middleware:

```php
// app/Http/Kernel.php


protected $routeMiddleware = [
  // ...
  'save-token' => \TokenAuth\Http\Middleware\SaveAuthToken::class,
];

// routes/api.php

Route::post('/users', function($request){
  // token is saved before calling this function
})->middleware(['auth:token', 'save-token']);
```

This might be useful to prevent multiple saves to the database: If a refresh token is used (&rArr; `TokenAuth::rotateRefreshToken(...)`) it is automatically revoked and saved. In this case the token would be saved directly after the authentication and then again after it is being revoked.

For a more convenient usage you can also define a middleware group:

```php
// app/Http/Kernel.php

protected $middlewareGroups = {
  // ...
  'auth+save:token' => ['auth:token', 'save-token'],
  'auth+save:token-refresh' => ['auth:token-refresh', 'save-token'],

  // alternative example:
  'auth:token' => ['auth:token', 'save-token'],
  'auth:token;noSave' => ['auth:token'],
  ...
}


// routes/api.php

Route::post('/users', function($request){
  // token is saved before calling this function
})->middleware('auth+save:token');
```

---

[Next: Testing &rarr;](./testing.md)
