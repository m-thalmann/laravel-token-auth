# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Abilities

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

---

[Next: Expiration &rarr;](./expiration.md)
