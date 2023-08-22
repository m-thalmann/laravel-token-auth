# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Configuration

### Table of contents

- [Configuration options](#configuration-options)
- [Customize token class](#customize-token-class)
- [Customize guard](#customize-guard)

### Configuration options

There are a couple of options you can set inside of the `tokenAuth.php` config-file:

- `expiration_minutes` - Amount of minutes until a token from a given type expires per default. If no value (or null) is set for a type, it does not expire per default.
- `prune_revoked_after_hours` - Amount of minutes that have to go by until a revoked token from a given type can be automatically deleted from the database
- `run_migrations` - Whether or not to run the migrations. If you want to customize them you have to set this to `false` (see below)

> To edit the config-file you have to publish it, like described [here](./README.md#customizing-the-package)

### Customize token class

To customize the used token class you have two options: Either you extend the existing model or you create your own class. For most use cases option 1 should be the preferred one.

After defining your token class you have to register it in one of the application's service providers (typically in the `AuthServiceProvider`):

```php
use App\Models\AuthToken;

class AuthServiceProvider extends ServiceProvider
{
  // ...

  public function register()
  {
    parent::register();

    // ...

    TokenAuth::useAuthToken(AuthToken::class);
  }
}
```

#### Extending the existing model

First define your custom model:

```php
use TokenAuth\Models\AuthToken as BaseAuthToken;

class AuthToken extends BaseAuthToken
{
  // ...
}
```

> Make sure that if you have to modify the `$casts`, `$hidden` or `$attributes` properties you append to those in the constructor (so not to overwrite the already set values)

> If you need to change the structure of the migration you have to publish (as described [here](./README.md#customizing-the-package)) and modify it (make sure you set the `run_migrations` configuration to `false`).

Then register it as described above.

#### Creating your own class

Since the interface of an auth token is defined within the `TokenAuth\Contracts\AuthTokenContract` interface you are free to define your own class. You are not bound to creating Eloquent models either.

> You can use the `TokenAuth\Concerns\AuthTokenHelpers` trait to implement some of the trivial methods for you

```php
use TokenAuth\Concerns\AuthTokenHelpers;
use TokenAuth\Contracts\AuthTokenContract;

class MyAuthToken implements AuthTokenContract
{
  use AuthTokenHelpers;

  // ... implement the missing methods
}
```

In most cases it will be required to create your own `TokenAuth\Contracts\AuthTokenBuilderContract` class to return from the `create()` method.

Then you once again have to register the class as described above.

### Customize guard

If you want to customize the way a token is retrieved from the request, how it is validated or any other aspect of the authentication using tokens you customize the token guard.

For this you have to extend the existing guard and register it once again:

```php
use TokenAuth\Contracts\AuthTokenContract;
use TokenAuth\Support\TokenGuard as BaseTokenGuard;

class TokenGuard extends BaseTokenGuard
{
  // ... overwrite some of the methods
  // some examples:

  protected function getTokenFromRequest(Request $request): ?string
  {
    return $request->header('X-Auth-Token');
  }

  protected function isValidToken(?AuthTokenContract $token): bool
  {
    // some additional validation...

    return parent::isValidToken($token);
  }

  protected function handleDetectedReuse(AuthTokenContract $token): void
  {
    parent::handleDetectedReuse($token);

    // do some logging...
  }
}
```

Then register it (same as above):

```php
use App\Models\AuthToken;

class AuthServiceProvider extends ServiceProvider
{
  // ...

  public function register()
  {
    parent::register();

    // ...

    TokenAuth::useTokenGuard(AuthToken::class);
  }
}
```

---

[Next: Testing &rarr;](./05-testing.md)
