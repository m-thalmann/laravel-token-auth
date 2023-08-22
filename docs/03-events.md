# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Events

### Table of contents

- [TokenAuthenticated](#tokenautheventstokenauthenticated)
- [RevokedTokenReused](#tokenautheventsrevokedtokenreused)

### `TokenAuth\Events\TokenAuthenticated`

This event is triggered after a token is used successfully for authenticating a user. The event receives the used token. Also the user is not yet set to the authentication at this time, so you will need to call the `getAuthenticatable()` function on the token if you want to access the user (see example in `RevokedTokenReused`-event).

Example:

```php
namespace App\Listeners;

use TokenAuth\Events\TokenAuthenticated;

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

This event is triggered whenever a token that was revoked before is reused. Same as above the user is not yet set.

Example:

```php
namespace App\Listeners;

use TokenAuth\Events\RevokedTokenReused;

// ...

class RevokedTokenReusedListener
{
  public function handle(RevokedTokenReused $event)
  {
    $token = $event->token;

    $user = $token->getAuthenticatable();

    // send user warning notification
    $user->notify(new RevokedTokenReusedNotification($token));
  }
}
```

---

[Next: Configuration &rarr;](./04-configuration.md)
