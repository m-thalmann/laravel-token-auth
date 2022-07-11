<h1 align="center">Laravel Token Auth</h1>

<p align="center">
<a href="https://github.com/m-thalmann/laravel-token-auth/actions"><img src="https://github.com/m-thalmann/laravel-token-auth/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://codecov.io/gh/m-thalmann/laravel-token-auth"><img src="https://codecov.io/gh/m-thalmann/laravel-token-auth/branch/main/graph/badge.svg?token=TIFI7QGGMB" alt="codecov"></a>
<a href="https://packagist.org/packages/m-thalmann/laravel-token-auth"><img src="https://img.shields.io/packagist/dt/m-thalmann/laravel-token-auth" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/m-thalmann/laravel-token-auth"><img src="https://img.shields.io/packagist/v/m-thalmann/laravel-token-auth" alt="Latest Stable Version"></a>
<a href="https://github.com/m-thalmann/laravel-token-auth"><img src="https://img.shields.io/github/license/m-thalmann/laravel-token-auth" alt="License"></a>
</p>

- [Introduction](#introduction)
- [Documentation](#documentation)
  - [Installation](#installation)
  - [Migration](#migration)
  - [Quick start](#quick-start)
    - [Protect routes](#protect-routes)
    - [Revoke tokens](#revoke-tokens)
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

**Detailed documentation:** [docs/README.md](docs/README.md)

### Installation

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

  [$refreshToken, $accessToken] = TokenAuth::createTokenPair(
    $refreshTokenName,
    $accessTokenName
  );

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

  [$refreshToken, $accessToken] = TokenAuth::rotateRefreshToken(
    $accessTokenName
  );

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

#### Protect routes

```php
Route::get('/private', function () {
  // only allows access tokens ...
})->middleware('auth:token');

Route::get('/private-refresh-token', function () {
  // only allows refresh tokens ...
})->middleware('auth:token-refresh');
```

#### Revoke tokens

```php
Route::get('/revoke/{token}', function (AuthToken $token) {
  $token->revoke()->save();
})->middleware('auth:token-refresh');
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
