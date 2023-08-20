# Laravel Token Auth

## Table of contents

1. [Protecting routes](./01-protecting-routes.md)
1. [Creating tokens](./02-creating-tokens.md)
1. [Events](./03-events.md)
1. [Configuration](./04-configuration.md)
1. [Testing](./05-testing.md)

## Setting up

### Installing the package

```
composer require m-thalmann/laravel-token-auth
```

Afterwards run:

```
php artisan migrate
```

If you want to customize the migrations, only run this command _afterwards_.

### Customizing the package

> See [Configuration](./04-configuration.md) for more information.

```
php artisan vendor:publish --provider="TokenAuth\TokenAuthServiceProvider"
```

If you only want to customize parts you can run the following:

- **Migrations**: `php artisan vendor:publish --tag="token-auth-migrations"`

- **Configuration**: `php artisan vendor:publish --tag="token-auth-config"`

### Adding the tokens to your user model

```php
use TokenAuth\Concerns\HasAuthTokens;

class User extends Authenticatable
{
  use HasAuthTokens;

  // ...
}
```

### Adding the middleware (optional)

```php
// app/Http/Kernel.php

protected $routeMiddleware = [
  // ...
  'ability' => \TokenAuth\Http\Middleware\CheckForAnyTokenAbility::class, // must have one of the specified abilities
  'abilities' => \TokenAuth\Http\Middleware\CheckForTokenAbilities::class, // must have all specified abilities
];
```

### Configuring the default guard (optional)

You can define the default guard used when authenticating. This way you dont have to specify it, when using the middleware.

E.g. `->middleware('auth')` instead of `->middleware('auth:token-access')`

```php
// config/auth.php

return [
  // ...

  'defaults' => [
    'guard' => 'token-access', // any of the guards
    // ...
  ],

  // ...

  'guards' => [
    // ...

    // Will be replaced by the package.
    // Needed to create policies with artisan command.
    'token-access' => [
      'provider' => 'users',
    ],
  ],

  // ...
];
```
