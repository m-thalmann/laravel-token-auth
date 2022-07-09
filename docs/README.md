# Laravel Token Auth

Laravel Token Auth provides functionality to authenticate Laravel APIs using access and refresh tokens.

It is heavily inspired by [Laravel Sanctum](https://github.com/laravel/sanctum).

## Table of contents

- [Creating tokens](./creating_tokens.md)
  - [Abilities](./abilities.md)
  - [Expiration](./expiration.md)
- [Revoking tokens](./revoking.md)
- [Protecting routes](./protecting_routes.md)
- [Events](./events.md)
- [Commands](./commands.md)
- [Configuration](./configuration.md)
- [Testing](./testing.md)

## Installation

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

Next you should run the migrations:

```
php artisan migrate
```

At last add the `HasAuthTokens` trait to the Eloquent user model:

```php
use TokenAuth\Traits\HasAuthTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
  use HasAuthTokens;

  // ...
}
```

---

[Next: Creating tokens &rarr;](./creating_tokens.md)
