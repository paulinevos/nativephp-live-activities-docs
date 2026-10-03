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

Use a unique key for each active activity. `start(): void` stores the native ID
on the same instance; `update()` and `end()` operate on that ID. Updates replace
the content; ending removes the presentation immediately.
Content builders are immutable, and progress ranges from `0.0` to `1.0`.

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
| `toArray()` | Serializable content with optional icon/color omitted when unset |

Keys must be nonblank and at most 80 Unicode characters. The complete JSON object
containing key, content, and optional deep link is limited to 3800 UTF-8 bytes,
including JSON overhead. Both PHP and native entry points validate payloads.

An update replaces **all** content. Include subtitle, progress, icon, and color in
every update where you want to retain them. See [icons](/icons) for defaults and
platform-specific color behavior.

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
