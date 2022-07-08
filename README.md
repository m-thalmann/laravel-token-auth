# Laravel Token Auth

- [Introduction](#introduction)
- [Documentation](#documentation)
  - [Installation](#installation)
  - [Migration](#migration)
  - [Quick start](#quick-start)
  - [Abilities](#abilities)
  - [Expiration](#expiration)
  - [Events](#events)
    - [`TokenAuth\Events\TokenAuthenticated`](#tokenautheventstokenauthenticated)
    - [`TokenAuth\Events\RevokedTokenReused`](#tokenautheventsrevokedtokenreused)
  - [Commands](#commands)
    - [`tokenAuth:prune-expired <type> --hours=<hours expired>`](#tokenauthprune-expired-type---hourshours-expired)
  - [Configuration](#configuration)
    - [Custom Token Model](#custom-token-model)
    - [Define how the token is retrieved from the request](#define-how-the-token-is-retrieved-from-the-request)
    - [Define an additional validator on the token](#define-an-additional-validator-on-the-token)
    - [Ignoring migrations](#ignoring-migrations)
    - [Disable auto-saving of token on authentication](#disable-auto-saving-of-token-on-authentication)
- [License](#license)

## Introduction

Laravel Token Auth provides functionality to authenticate Laravel APIs using access and refresh tokens.

It is heavily inspired by [Laravel Sanctum](https://github.com/laravel/sanctum).

### Refresh tokens

Refresh tokens are used to create new access tokens. This way an access token can have only a short expiration time without the need for the user to login again.

To keep these refresh tokens save we can implement refresh token rotation. When a new access token is requested using a refresh token, the new access token and a new refresh token is returned. The used refresh token is then revoked but kept in the database. This way it can be detected if a refresh token is reused.

For more details see: https://auth0.com/blog/refresh-tokens-what-are-they-and-when-to-use-them/

## Documentation

**[`^ back to top ^`](#)**

### Installation

_**Info:** The package is not yet published to Packagist, therefore you have to add the repository to your composer.json for now:_

```jsonc
// composer.json

"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/m-thalmann/laravel-token-auth"
    }
]
```

```
composer require m-thalmann/laravel-token-auth
```

If you want to customize the migrations, configuration and/or translations run the publish command:

```
php artisan vendor:publish --provider="TokenAuth\TokenAuthServiceProvider"
```

If you only want to customize parts you can run the following:

- **Migrations**: `php artisan vendor:publish --tag="token-auth-migrations"`

- **Configuration**: `php artisan vendor:publish --tag="token-auth-config"`

- **Translations**: `php artisan vendor:publish --tag="token-auth-lang"`

### Migration

Next you should run the migrations:

```
php artisan migrate
```

### Quick start

**[`^ back to top ^`](#)**

Add the `HasAuthTokens` trait to the Eloquent user model:

```php
use TokenAuth\Traits\HasAuthTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
  use HasAuthTokens;

  // ...
}
```

Add the following routes for authentication:

```php
use TokenAuth\TokenAuth;

Route::post('/login', function ($request) {
  $credentials = $request->validate(...); // validate credentials

  if (!Auth::once($credentials)) {
    throw new AuthorizationException();
  }

  [$refreshToken, $accessToken] = TokenAuth::createTokenPair($refreshTokenName, $accessTokenName);

  return [
    'refresh_token' => $refreshToken->plainTextToken,
    'access_token' => $accessToken->plainTextToken,
  ];
});

Route::post('/logout', function () {
  $token = auth()
    ->user()
    ->currentToken();

  $token->deleteAllTokensFromSameGroup();
})->middleware('auth:token');

Route::post('/refresh', function () {
  // ...

  [$refreshToken, $accessToken] = TokenAuth::rotateRefreshToken($accessTokenName);

  return [
    'refresh_token' => $refreshToken->plainTextToken,
    'access_token' => $accessToken->plainTextToken,
  ];
})->middleware('auth:token-refresh');

Route::post('/tokens', function () {
  // ...

  $accessToken = TokenAuth::createAccessToken($accessTokenName);

  return [
    'access_token' => $accessToken->plainTextToken,
  ];
})->middleware('auth:token-refresh');
```

Protect routes:

```php
Route::get('/private', function () {
  // only allows access tokens ...
})->middleware('auth:token');

Route::get('/private-refresh-token', function () {
  // only allows refresh tokens ...
})->middleware('auth:token-refresh');
```

Revoke tokens:

```php
Route::get('/revoke/{token}', function (AuthToken $token) {
  $token->revoke()->save();
})->middleware('auth:token-refresh');
```

### Abilities

**[`^ back to top ^`](#)**

Tokens can have certain abilities which are associated with them. When creating the tokens pass them as an array of strings:

```php
TokenAuth::createTokenPair($refreshTokenName, $accessTokenName, [
  ['refresh-ability'], // abilities for refresh token
  ['access-ability'], // abilities for access token
]);

// the abilities stay the same for the refresh token
TokenAuth::rotateRefreshToken(
  $accessTokenName,
  ['access-ability'] // abilities for the new access token
);

TokenAuth::createAccessToken(
  $accessTokenName,
  ['access-ability'] // abilities for the new access token
);
```

The tokens abilities can then be queried by doing the following:

```php
if (
  auth()
    ->user()
    ->currentToken()
    ->tokenCan('create-user')
) {
  // token has the 'create-user' ability
}
```

You can also register middleware to handle this for a route:

```php
// app/Http/Kernel.php

protected $routeMiddleware = [
  // ...
  'ability' => \TokenAuth\Http\Middleware\CheckForAnyAbility::class, // must have one of the specified abilities
  'abilities' => \TokenAuth\Http\Middleware\CheckAbilities::class, // must have all specified abilities
];

// routes/api.php

Route::post('/users', function($request){
  // token has either 'admin' or 'create-user' ability
})->middleware(['auth:token', 'ability:admin,create-user']);
```

### Expiration

**[`^ back to top ^`](#)**

The tokens have predefined expiration times (defined in the config).

**Configuration:**

- `tokenAuth.refresh_token_expiration` - Default: _7 days_
- `tokenAuth.access_token_expiration` - Default: _10 minutes_

If you want to change the value for a single token you can do that this way (defined as minutes):

```php
TokenAuth::createTokenPair($refreshTokenName, $accessTokenName, $pairAbilities, [
  60, // expiration time of refresh token
  60, // expiration time of access token
]);

// the abilities stay the same for the refresh token
TokenAuth::rotateRefreshToken($accessTokenName, $accessTokenAbilities, [
  60, // expiration time of refresh token
  60, // expiration time of access token
]);

TokenAuth::createAccessToken(
  $accessTokenName,
  $accessTokenAbilities,
  60 // expiration time of access token
);
```

**Possible values:**

- `<number>`: minutes until expiration
- `null`: use value from config
- `-1`: token does not expire

### Events

**[`^ back to top ^`](#)**

#### `TokenAuth\Events\TokenAuthenticated`

This event is triggered after a token is used successfully for authenticating a user. The event receives the used token before the `last_used` timestamp is set and the model is saved (saved unless the `TokenAuth::dontSaveTokenOnAuthentication()` was called; see below). Also the user is not yet set in the authentication at this time, so you will need to call the `tokenable` relationship on the token if you want to access the user.

#### `TokenAuth\Events\RevokedTokenReused`

This event is triggered whenever a token that was revoked before is reused. The event receives the used token before all of the tokens from the same group are deleted. Same as above the user is not yet set.

### Commands

**[`^ back to top ^`](#)**

#### `tokenAuth:prune-expired <type> --hours=<hours expired>`

This command is used to delete expired and/or revoked tokens from the database that have been expired/revoked for more than the defined amount of hours.

You can (and probably should) schedule this command to run automatically:

```php
$schedule->command('tokenAuth:prune-expired access --hours=24')->daily();
$schedule->command('tokenAuth:prune-expired refresh --hours=168')->daily(); // 7 days
```

### Configuration

**[`^ back to top ^`](#)**

#### Custom Token Model

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

#### Define how the token is retrieved from the request

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

#### Define an additional validator on the token

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

#### Ignoring migrations

If you do not want the migrations to be registered and run you can disable them by adding this to you `AuthServiceProvider`:

```php
public function boot(){
  // ...

  TokenAuth::ignoreMigrations();
}
```

#### Disable auto-saving of token on authentication

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

This might be useful to prevent multiple saves to the database: If a refresh token is used (=> `TokenAuth::rotateRefreshToken(...)`) it is automatically revoked and saved. In this case the token would be saved directly after the authentication and then again after it is being revoked.

For a more convenient usage you can also define a middleware group:

```php
// app/Http/Kernel.php

protected $middlewareGroups = {
  // ...
  'auth+save:token' => ['auth:token', 'save-token'],
  'auth+save:token-refresh' => ['auth:token-refresh', 'save-token'],
}

// routes/api.php

Route::post('/users', function($request){
  // token is saved before calling this function
})->middleware('auth+save:token');
```

## License

**[`^ back to top ^`](#)**

This package is open-sourced software licensed under the [MIT license](LICENSE).

Parts of this work are derived, modified or copied from [Laravel Sanctum](https://github.com/laravel/sanctum) which is also licensed under the MIT license:

> The MIT License (MIT)
>
> Copyright (c) Taylor Otwell
>
> Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
>
> The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
>
> THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
