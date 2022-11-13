# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Events

#### `TokenAuth\Events\TokenAuthenticated`

This event is triggered after a token is used successfully for authenticating a user. The event receives the used token. Also the user is not yet set in the authentication at this time, so you will need to call the `tokenable` relationship on the token if you want to access the user (see example in `RevokedTokenReused`-event).

Example:

```php
namespace App\Listeners;

// ...

class TokenAuthenticatedListener
{
  public function handle(TokenAuthenticated $event)
  {
    $token = $event->token;

    $lastIp = request()->getClientIp();
    $ipHost = @gethostbyaddr($lastIp);

    if ($ipHost === false || Str::is($ipHost, $lastIp)) {
      $ipHost = null;
    }

    $token->update([
      'last_ip' => $lastIp,
      'last_ip_host' => $ipHost,
      'last_user_agent' => request()->userAgent(),
    ]);
  }
}
```

#### `TokenAuth\Events\RevokedTokenReused`

This event is triggered whenever a token that was revoked before is reused. The event receives the used token before all of the tokens from the same group are deleted. Same as above the user is not yet set.

Example:

```php
namespace App\Listeners;

// ...

class RevokedTokenReusedListener
{
  public function handle(RevokedTokenReused $event)
  {
    $token = $event->token;

    $user = $token->tokenable;

    // send user warning notification
    $user->notify(new RevokedTokenReusedNotification($token));
  }
}
```

---

[Next: Configuration &rarr;](./configuration.md)
