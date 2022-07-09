# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Events

#### `TokenAuth\Events\TokenAuthenticated`

This event is triggered after a token is used successfully for authenticating a user. The event receives the used token before the `last_used` timestamp is set and the model is saved (saved unless the `TokenAuth::dontSaveTokenOnAuthentication()` was called; see [configuration](./configuration.md)). Also the user is not yet set in the authentication at this time, so you will need to call the `tokenable` relationship on the token if you want to access the user.

Example:

```php
namespace App\Listeners;

// ...

class TokenAuthenticatedListener
{
  public function handle(TokenAuthenticated $event)
  {
    $token = $event->token;

    $token->last_ip = request()->getClientIp();
    $token->last_user_agent = request()->userAgent();

    $ipHost = @gethostbyaddr($token->last_ip);

    if ($ipHost === false || Str::is($ipHost, $token->last_ip)) {
      $ipHost = null;
    }

    $token->last_ip_host = $ipHost;
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

[Next: Commands &rarr;](./commands.md)
