# Capability checks

Check availability before offering the feature:

```php
$capabilities = LiveActivities::capabilities();

if ($capabilities->supported && $capabilities->enabled) {
    // The platform supports activities and the required permission is enabled.
}
```

`promoted` reports whether Android promotion can be requested, not whether an
individual notification was promoted. `push` is currently `false` on both platforms.

| Property | Meaning |
|---|---|
| `platform` | `ios`, `android`, or `unsupported` |
| `supported` | The platform supports this plugin's activity feature |
| `enabled` | Required activity/notification settings are currently enabled |
| `promoted` | Android promotion can be requested, not guaranteed |
| `push` | Always `false` in the current implementation |

Outside a NativePHP runtime, capabilities report `unsupported` with all flags
false. Mutations fail with `unavailable`. Checks do not request permissions and
are not a guarantee that a later start will succeed: settings can change between calls.
