# Laravel Token Auth

[&larr; Return to table of contents](./README.md)

## Commands

#### `tokenAuth:prune-expired <type> --hours=<hours expired>`

This command is used to delete expired and/or revoked tokens from the database that have been expired/revoked for more than the defined amount of hours.

You can (and probably should) schedule this command to run automatically:

```php
$schedule->command('tokenAuth:prune-expired access --hours=24')->daily();
$schedule->command('tokenAuth:prune-expired refresh --hours=168')->daily(); // 7 days
```

Execute the following for help:

```
php artisan tokenAuth:prune-expired --help
```

---

[Next: Configuration &rarr;](./configuration.md)
