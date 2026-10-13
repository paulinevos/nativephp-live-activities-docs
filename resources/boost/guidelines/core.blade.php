## vos/nativephp-live-activities-free

Live Activities and Live Updates for NativePHP Mobile. Starts, updates and ends
iOS Live Activities and Android ongoing progress notifications from PHP.

Calls run in-process on the device through the native bridge, so they only work inside
a running NativePHP app. On web and CLI, `capabilities()` reports unsupported and the other
methods throw.

### Installation

```bash
composer require vos/nativephp-live-activities-free:dev-main
```

Register the service provider in the host app's
`App\Providers\NativeServiceProvider::plugins()` list, then build with NativePHP
so both native bridges and the iOS widget extension are included.

### Usage

Content is built with an immutable builder; each call returns a new instance.
`start()` takes a caller-chosen key that identifies the activity; `update()` and
`end()` take the native `id` returned by `start()`.

@verbatim
<code-snippet name="Live Activity lifecycle" lang="php">
use Vos\LiveActivities\ActivityContent;
use Vos\LiveActivities\Facades\LiveActivities;

$content = ActivityContent::make('Order placed')
    ->subtitle('Preparing your food')
    ->progress(0.1);

$activity = LiveActivities::start('order-123', $content);

$activity = LiveActivities::update($activity->id, $content
    ->subtitle('On the way')
    ->progress(0.6));

LiveActivities::end($activity->id);
</code-snippet>
@endverbatim

### Available methods

- `LiveActivities::start(string $key, ActivityContent $content): Activity` — start an activity. `$key` is yours and must be unique among active activities (max 80 characters).
- `LiveActivities::update(string $id, ActivityContent $content): Activity` — replace the content of an active activity.
- `LiveActivities::end(string $id): void` — end an active activity.
- `LiveActivities::all(): ActivityCollection` — the currently active activities. Note this is `all()`, not `list()`.
- `LiveActivities::capabilities(): Capabilities` — platform support and permission state.

### Types

`Activity` is a readonly object with public `id`, `key`, `content` and `state`
properties. `ActivityCollection` is `Countable` and iterable over `Activity`, and
has `toArray()`.

`ActivityContent::make(string $title)` starts a builder; `->subtitle(string)` and
`->progress(float)` return new instances. Title max 120 characters, subtitle max
240 characters (may be empty), progress between 0 and 1 inclusive. Title and key
must not be blank. The encoded key plus content must stay under 3800 bytes.

`Capabilities` has public `platform` (`ios`, `android` or `unsupported`),
`supported`, `enabled`, `push` and `promoted` booleans. Check it before starting
an activity:

@verbatim
<code-snippet name="Checking capabilities" lang="php">
use Vos\LiveActivities\Facades\LiveActivities;

$capabilities = LiveActivities::capabilities();

if (! $capabilities->supported || ! $capabilities->enabled) {
    // Unsupported platform, or the user has not allowed notifications.
    return;
}
</code-snippet>
@endverbatim

`promoted` means Android promotion may be *requested*, not that it was granted —
the system decides per notification, and falls back to an ordinary progress
notification. `push` is always false: remote APNs/FCM delivery and push-to-start
are not part of this contract.

### Errors

Every failure throws `Vos\LiveActivities\Exceptions\ActivityException`, which
carries a readonly `errorCode`: `invalid_payload`, `payload_too_large`,
`unavailable` (no NativePHP runtime or plugin missing), or `native_error`.

@verbatim
<code-snippet name="Handling failures" lang="php">
use Vos\LiveActivities\Exceptions\ActivityException;
use Vos\LiveActivities\Facades\LiveActivities;

try {
    LiveActivities::start('order-123', $content);
} catch (ActivityException $exception) {
    report($exception);
}
</code-snippet>
@endverbatim

### Platform notes

Android requires the `POST_NOTIFICATIONS` runtime permission, granted by the host
app; without it `enabled` is false and starting fails. Minimum versions are
Android API 29 and iOS 18.0. Signed device builds need an extension provisioning
identity; simulator builds need no account.
