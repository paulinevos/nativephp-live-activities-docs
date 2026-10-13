# PHP API

```php
use Vos\LiveActivities\ActivityContent;
use Vos\LiveActivities\Icons\LucideUtensils;
use Vos\LiveActivities\Icons\LucideTruck;
use Vos\LiveActivities\Facades\LiveActivities;
use Vos\LiveActivities\LiveActivity;

$activity = LiveActivity::make('delivery-42',
    ActivityContent::make('Preparing your order')->subtitle('In the kitchen')
        ->icon(new LucideUtensils)->color('#F97316')
);
$activity->start();

$activity->update(
    ActivityContent::make('On the way')->progress(0.5)
        ->icon(new LucideTruck)->color('#06B6D4')
);

LiveActivity::all();
LiveActivities::capabilities();
$activity->end();
```

Give each activity a unique key. Call `start()` to show it, `update()` to replace
what it shows, and `end()` to remove it. Progress ranges from `0.0` to `1.0`.

`LiveActivity` has public readable properties: readonly `key`, and `id`, `content`,
`link`, and `state` with private setters. `id` is `null` until start succeeds;
`state` is `ActivityState::Pending`, `Active`, or `Ended`. State reflects the last
successful operation, not independent system dismissal. Failed operations preserve
the previous local state. Starting twice, updating/ending before start, or operating
on an ended instance throws `ActivityException` with `invalid_state`.

`LiveActivity::all()` returns an iterable, countable `LiveActivityCollection` of
active instances that can be updated or ended after an app restart. It also
supports `toArray()`. The existing facade API remains supported:
`LiveActivities::start($key, $content)`, `update($id, $content)`, `end($id)`, and
`all()` returning readonly `Activity` snapshots.

## Lifecycle and collections

| Method | Result |
|---|---|
| `LiveActivity::make(string $key, ActivityContent $content)` | A pending `LiveActivity` instance |
| `$activity->withLink(string $url)` | The same pending instance; see [deep links](/deep-links) |
| `$activity->start(): void` | Starts the native activity and stores its ID |
| `$activity->update(ActivityContent $content): void` | Replaces the active content |
| `$activity->end(): void` | Ends the activity and removes its presentation |
| `LiveActivity::all(): LiveActivityCollection` | Actionable active instances read from the native platform |
| `$activity->toArray(): array` | ID (when set), key, content, state, and optional `deep_link` |

```php
foreach (LiveActivity::all() as $activity) {
    if ($activity->key === 'delivery-42') {
        $activity->end();
    }
}
```

Listing observes native state; there is no PHP activity cache. A system dismissal
may invalidate a local instance. See [errors](/errors) before retrying a failed operation.

## Content builders

`ActivityContent` is immutable: each builder returns a new instance.

| Method | Accepted value / default |
|---|---|
| `make(string $title)` | Nonblank title, at most 120 Unicode characters |
| `subtitle(string $subtitle)` | At most 240 Unicode characters; defaults to an empty string |
| `progress(float $progress)` | Finite number from `0.0` to `1.0`; defaults to `0` |
| `icon(object|string $icon)` | Bundled icon instance/name, registered custom icon, or prepared runtime icon |
| `color(ActivityColor|string $color)` | Six-digit `#RRGGBB` hex color, normalized to uppercase |
| `countdown(DateTimeInterface $target)` | Time to count down to; accepts Carbon or a PHP date object |
| `eta(DateTimeInterface $target)` | Expected finish time, shown in the user's local time zone |
| `toArray()` | Serializable content; optional icon, color, and time settings are omitted when unset |

Keys must be nonblank and at most 80 Unicode characters. The complete JSON object
containing key, content, and optional deep link is limited to 3800 UTF-8 bytes,
including JSON overhead. Both PHP and native entry points validate payloads.

An update replaces **all** content. Include subtitle, progress, icon, color, and
the countdown or ETA in every update where you want to retain them. See
[icons](/icons) for defaults and platform-specific color behavior.

## ETA and countdowns

::: info Version support
ETA and countdowns are not included in v1.2.0. Use a package release containing
this feature and rebuild your native app before using these methods.
:::

Show how much time is left until a task is expected to finish:

```php
use Vos\LiveActivities\ActivityContent;
use Vos\LiveActivities\LiveActivity;

$readyAt = now()->addMinutes(8);

$activity = LiveActivity::make('pasta-timer',
    ActivityContent::make('Cooking pasta')->countdown($readyAt)
);
$activity->start();
```

### When does the countdown start?

**`start()` shows the countdown; it does not reset the clock.** You pass a finish
time, not a duration. In this example, the eight minutes begin when `$readyAt`
is calculated. If you wait two minutes before calling `start()`, the countdown
shows about six minutes remaining.

Once visible, the countdown keeps ticking even when the app is in the background.
You do not need to send an update every second.

### Show the finish time instead

Use `eta()` to show the expected finish time, such as “18:30”, in the user's
local time zone:

```php
$activity->update(
    ActivityContent::make('Cooking pasta')->eta($readyAt)
);
```

Using the same `$readyAt` keeps the original deadline. Switch back with
`countdown($readyAt)`, or pass a different date to change the expected finish time.
Calling `countdown(now()->addMinutes(8))` again would move the deadline eight
minutes ahead of that new call.

Both methods accept Carbon or any `DateTimeInterface`, to second precision.
Dates in the past are allowed. Neither method changes numeric progress.

### What happens at zero?

**The activity does not end automatically, and no alarm sounds.**

- **iOS:** the countdown holds at zero.
- **Android:** the notification countdown continues into negative time. A promoted
  status-bar chip may stop showing the time; Android controls its appearance.

When the task is done, remove the activity while your app is running:

```php
$activity->end();
```

Automatic ending at the deadline is not supported by this release. A countdown
keeps the display moving; it does not keep your app running or schedule PHP code.

### Remove the time without ending the activity

Send new content without `countdown()` or `eta()`:

```php
$activity->update(
    ActivityContent::make('Checking the pasta')->subtitle('Almost ready')
);
```

This replaces the previous content, including its time display, without ending
the activity. The user or operating system can still dismiss it.

### Try the cooking timer demo

In the [package demo](https://github.com/paulinevos/nativephp-live-activities/tree/main/examples/demo),
choose **Preview cooking timer** for a 90-second countdown. It works without the
mock server or an account. Go Home or lock the device to watch it, then return
to switch **Show ETA** / **Show countdown** without resetting the deadline.
Wait past zero to see your platform's behavior, then choose **End cooking timer**.

## Facade API

Use the facade when you manage native IDs yourself. Start/update return readonly
`Activity` snapshots, rather than changing a `LiveActivity` instance.

```php
use Vos\LiveActivities\Facades\LiveActivities;

$snapshot = LiveActivities::start('delivery-42', $content);
$snapshot = LiveActivities::update($snapshot->id, $nextContent);
LiveActivities::end($snapshot->id);
```

| Method | Return type |
|---|---|
| `start(string $key, ActivityContent $content, ?string $deepLink = null)` | `Activity` |
| `update(string $id, ActivityContent $content)` | `Activity` |
| `end(string $id)` | `void` |
| `all()` | `ActivityCollection` of readonly snapshots |
| `capabilities()` | `Capabilities`; see [capability checks](/capabilities) |
| `prepareIcon(string $path, string $rendering = 'template', ?string $androidPath = null)` | `CustomIcon`; see [runtime artwork](/runtime-artwork) |

Snapshots expose `id`, `key`, `content`, `state`, and optional `deepLink`.
`ActivityCollection` is iterable, countable, and supports `toArray()`.
