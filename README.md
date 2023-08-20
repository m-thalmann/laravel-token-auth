<h1 align="center">Laravel Token Auth</h1>

<p align="center">
<a href="https://github.com/m-thalmann/laravel-token-auth/actions"><img src="https://github.com/m-thalmann/laravel-token-auth/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://codecov.io/gh/m-thalmann/laravel-token-auth"><img src="https://codecov.io/gh/m-thalmann/laravel-token-auth/branch/main/graph/badge.svg?token=TIFI7QGGMB" alt="codecov"></a>
<a href="https://packagist.org/packages/m-thalmann/laravel-token-auth"><img src="https://img.shields.io/packagist/dt/m-thalmann/laravel-token-auth" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/m-thalmann/laravel-token-auth"><img src="https://img.shields.io/packagist/v/m-thalmann/laravel-token-auth" alt="Latest Stable Version"></a>
<a href="https://github.com/m-thalmann/laravel-token-auth"><img src="https://img.shields.io/github/license/m-thalmann/laravel-token-auth" alt="License"></a>
</p>

## Table of contents

- [Introduction](#introduction)
- [Installation](#installation)
- [Quick start](#quick-start)
  - [Protect routes](#protect-routes)
  - [Revoke tokens](#revoke-tokens)
  - [Prune revoked / expired tokens](#prune-revoked--expired-tokens)

> **See the detailed documentation at: [/docs](/docs/README.md)**

## Introduction

Laravel Token Auth provides functionality to authenticate Laravel APIs using access and refresh tokens.

It is heavily inspired by [Laravel Sanctum](https://github.com/laravel/sanctum).

### Refresh tokens

Refresh tokens are used to create new access tokens. This way an access token can have only a short expiration time without the need for the user to login again.

To keep these refresh tokens save we can implement refresh token rotation. When a new access token is requested using a refresh token, the new access token and a new refresh token is returned. The used refresh token is then revoked but kept in the database. This way it can be detected if a refresh token is reused.

For more details see: https://auth0.com/blog/refresh-tokens-what-are-they-and-when-to-use-them/

## Installation

**[`^ back to top ^`](#)**

```
composer require m-thalmann/laravel-token-auth
```

If you want to customize the migrations, configuration run the publish command:

```
php artisan vendor:publish --provider="TokenAuth\TokenAuthServiceProvider"
```

If you only want to customize parts you can run the following:

- **Migrations**: `php artisan vendor:publish --tag="token-auth-migrations"`

- **Configuration**: `php artisan vendor:publish --tag="token-auth-config"`

Next you should run the migrations:

```
php artisan migrate
```

## Quick start

**[`^ back to top ^`](#)**

Add the `HasAuthTokens` trait to the Eloquent user model:

```php
use TokenAuth\Concerns\HasAuthTokens;

class User extends Authenticatable
{
  use HasAuthTokens;

  // ...
}
```

Add the following routes for authentication:

> **Info:** Of course you should create your own controllers for this. This is just a simplification.

```php
use TokenAuth\Enums\TokenType;
use TokenAuth\Facades\TokenAuth;
use TokenAuth\Models\AuthToken;

Route::post('/login', function (Request $request) {
  $credentials = $request->validate([
    'email' => 'required',
    'password' => 'required',
  ]);

  if (!Auth::once($credentials)) {
    throw new HttpException(401);
  }

  $tokenPair = TokenAuth::createTokenPair(auth()->user())->buildPair();

  return [
    'refresh_token' => $tokenPair->refreshToken->plainTextToken,
    'access_token' => $tokenPair->accessToken->plainTextToken,
  ];
});

Route::post('/logout', function () {
  AuthToken::deleteTokensFromGroup(TokenAuth::currentToken()->getGroupId());
})->middleware('auth:token-access');

Route::post('/refresh', function () {
  // ...

  $tokenPair = TokenAuth::rotateTokenPair(
    TokenAuth::currentToken()
  )->buildPair();

  return [
    'refresh_token' => $tokenPair->refreshToken->plainTextToken,
    'access_token' => $tokenPair->accessToken->plainTextToken,
  ];
})->middleware('auth:token-refresh');

Route::post('/tokens', function () {
  // ...

  /**
   * @var \TokenAuth\Concerns\HasAuthTokens
   */
  $user = auth()->user();

  $accessToken = $user->createToken(TokenType::ACCESS)->build();

  return [
    'access_token' => $accessToken->plainTextToken,
  ];
})->middleware('auth:token-refresh');
```

### Protect routes

```php
Route::get('/private', function () {
  // only allows access tokens ...
})->middleware('auth:token-access');

Route::get('/private-refresh-token', function () {
  // only allows refresh tokens ...
})->middleware('auth:token-refresh');
```

### Revoke tokens

```php
Route::get('/revoke/{token}', function (AuthToken $token) {
  $token->revoke()->store();
})->middleware('auth:token-refresh');
```

#### Prune revoked / expired tokens

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule) {
  // ...
  $schedule->command('model:prune')->daily();
}
```
