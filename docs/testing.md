# Testing your application

The plugin provides response and assertion helpers for NativePHP's `FakeBridge`,
registered automatically through its service provider. Use `ActivityFixture` to
create an activity for a scripted response:

```php
use Native\Mobile\Testing\Native;
use Vos\LiveActivities\ActivityContent;
use Vos\LiveActivities\Facades\LiveActivities;
use Vos\LiveActivities\Support\ActivityFixture;

$content = ActivityContent::make('On the way')->progress(0.5);
$activity = ActivityFixture::make('delivery-42', $content);
$bridge = Native::fakeBridge()->withLiveActivityStarted($activity);

// Exercise your application code that starts the activity.
LiveActivities::start('delivery-42', $content);

$bridge->assertLiveActivityStarted('delivery-42');
```

Fixtures default to the ID `test-activity`; pass `id: 'another-id'` when you need
distinct activities. More helpers for updates, completion, lists, and capabilities
are available in [the plugin source](https://github.com/paulinevos/nativephp-live-activities/blob/main/src/Support/TestingMacros.php). Responses are
scripted: start/update/end do not maintain a simulated activity list.

The fluent API uses the same fake. For linked activities, pass
`deepLink: 'myapp://orders/42'` to `ActivityFixture::make()`, configure the scheme
in your test, and assert `$bridge->assertLiveActivityLinked('delivery-42', 'myapp://orders/42')`.

See [NativePHP's testing documentation](https://nativephp.com/docs/mobile/4/testing/introduction)
for component tests and general bridge assertions.

## Response and assertion helpers

| Script a response | Assert a call |
|---|---|
| `withLiveActivityStarted(Activity $activity)` | `assertLiveActivityStarted(?string $key = null, ?callable $content = null)` |
| `withLiveActivityUpdated(Activity $activity)` | `assertLiveActivityUpdated(?string $id = null, ?callable $content = null)` |
| `withLiveActivityEnded(string $id)` | `assertLiveActivityEnded(?string $id = null)` |
| `withLiveActivities(Activity ...$activities)` | Use the standard bridge assertion for `LiveActivities.List` |
| `withLiveActivitiesCapabilities(Capabilities $capabilities)` | Use the standard bridge assertion for `LiveActivities.Capabilities` |

`assertLiveActivityLinked(string $key, string $url)` checks the link sent in Start.
The optional content callback receives `ActivityContent` and must return `true`
for a match. Fixtures are readonly `Activity` snapshots suitable for scripting
responses for either public API.

Tests using FakeBridge do not validate native rendering or OS permissions.
