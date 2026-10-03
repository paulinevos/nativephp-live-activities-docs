# Deep links

Set one tap destination before starting:

```php
$activity = LiveActivity::make('delivery-42', $content)
    ->withLink('myapp://orders/42?tab=tracking');
$activity->start();
```

`withLink()` configures and returns the same instance. The destination is fixed
once started and is preserved through content updates and activity listing. Without
a link, the existing app-opening behavior remains. Tapping does not end the activity.

Configure `NATIVEPHP_DEEPLINK_SCHEME=myapp` in your app, with a native route for
`/orders/{order}`. Alternatively, supply an HTTPS URL whose host matches
`NATIVEPHP_DEEPLINK_HOST`; associated-domain links need the corresponding Apple
and Android verification files. Rebuild after changing native deep-link configuration.
See [NativePHP deep links](https://nativephp.com/docs/mobile/4/digging-deeper/deep-links).

Links must be absolute URLs without credentials, ports, backslashes or whitespace;
encode spaces in query values. The link counts toward the 3800-byte activity payload
limit. Scheme or HTTPS-host configuration is checked before starting. The destination
screen handles authentication and missing records as it would for any other deep link.

On iOS, the link is attached to the Lock Screen and Dynamic Island presentations.
On Android, it is the ongoing notification's tap destination, independent of whether
the system promotes the notification to a Live Update. Each activity has its own
tap intent, including when two activities share a destination.

Facade callers can also pass `deepLink: 'myapp://orders/42'` to `start()`;
returned snapshots expose `deepLink` and serialize it as optional `deep_link`.
