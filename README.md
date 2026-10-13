# NativePHP Live Activities Free

Keep users up to date on deliveries and ongoing tasks with one PHP API.
NativePHP Live Activities provides local iOS Live Activities and Android ongoing
progress notifications, with optional Android promotion where the system allows it.

This free edition is based on the original **v1.0.0** release. The API and examples
below describe that version, including its eight bundled icons.

Want **custom icons, countdowns, ETA, deep links, and more**?
[Get the paid plugin](https://nativephp.com/plugins/vos/nativephp-live-activities).
The [full documentation](https://pauline-vos.nl/nativephp-live-activities-free/)
describes the paid edition; this README is the reference for the free edition.

## iOS

Show a title, subtitle, progress, and a colored icon on the Lock Screen and in the
Dynamic Island on supported devices. Activities remain visible while your app is
in the background.

![iOS demo](https://github.com/paulinevos/paulinevos.github.io/blob/main/public/iOS-demo.gif?raw=true)

## Android

Show an ongoing progress notification. On supported Android versions, the plugin
also requests promotion to a Live Update, including a status-bar chip. Promotion
depends on system support and user settings and is never guaranteed.

![Android demo](https://github.com/paulinevos/paulinevos.github.io/blob/main/public/Android-demo.gif?raw=true)

## Quick start

In an existing NativePHP Mobile app:

```sh
composer require vos/nativephp-live-activities-free

php artisan vendor:publish --tag=nativephp-plugins-provider
php artisan native:plugin:register vos/nativephp-live-activities-free
php artisan native:plugin:list
```

The VCS repository installs the free edition directly from Packagist; no paid-plugin
credentials are needed. NativePHP Mobile's own requirements still apply.
The free and paid editions share the same PHP namespace and cannot be installed
together. Remove the free edition before installing the paid plugin.

During Laravel's normal Composer install/update flow, successful package discovery
prints a short link to the paid edition. It is also shown by `artisan package:discover`,
but not by ordinary Artisan commands or quiet package discovery. Composer
`--no-scripts` or disabled Laravel auto-discovery skips this message.

Registration adds `Vos\LiveActivities\LiveActivitiesServiceProvider` to your
app's `App\Providers\NativeServiceProvider::plugins()` list. Then rebuild:

```sh
php artisan native:run ios
# Or:
php artisan native:run android
```

The build hook installs the iOS widget extension automatically. Signed iOS device
builds need provisioning for both the host app and its extension; simulator builds
need no Apple developer account. On Android 13+, your app must request notification
permission before starting an activity. This plugin does not display permission prompts.

## PHP API

```php
use Vos\LiveActivities\ActivityContent;
use Vos\LiveActivities\ActivityIcon;
use Vos\LiveActivities\Facades\LiveActivities;

$activity = LiveActivities::start('delivery-42',
    ActivityContent::make('Preparing your order')->subtitle('In the kitchen')
        ->icon(ActivityIcon::Utensils)->color('#F97316')
);

LiveActivities::update($activity->id,
    ActivityContent::make('On the way')->progress(0.5)
        ->icon(ActivityIcon::Truck)->color('#06B6D4')
);

LiveActivities::all();
LiveActivities::capabilities();
LiveActivities::end($activity->id);
```

Use a unique key for each active activity and the returned native ID for updates
and completion. Updates replace the content; ending removes the presentation immediately.
Content builders are immutable, and progress ranges from `0.0` to `1.0`.

### Icons and accent colors

Choose an icon using the `ActivityIcon` enum or its Lucide name:

```php
$content = ActivityContent::make('Delivered')
    ->progress(1)
    ->icon('circle-check')
    ->color('#22C55E');
```

The bundled set includes `circle-dashed`, `utensils`, `truck`, `circle-check`,
`package`, `clock`, `download`, and `navigation`. These are local
[Lucide](https://lucide.dev/) icons; no network requests or additional runtime
dependencies are needed. Their license notices ship in `resources/icons/LICENSE`.

Colors must use six-digit `#RRGGBB` hex notation. Unknown icon names and invalid
colors throw `ActivityException`. Both settings are optional: the default icon is
`circle-dashed`, with white on iOS and the system's default notification accent
on Android. Updates replace the entire content, including icon and color, so
include them in each update to retain your styling.

On iOS, the accent colors the icon, progress bar, and percentage. On Android, it
sets the notification accent; status-bar icons stay monochrome and the system
controls their tint and final presentation. Custom icon files require the
[paid edition](https://nativephp.com/plugins/vos/nativephp-live-activities).

Check availability before offering the feature:

```php
$capabilities = LiveActivities::capabilities();

if ($capabilities->supported && $capabilities->enabled) {
    // The platform supports activities and the required permission is enabled.
}
```

`promoted` reports whether Android promotion can be requested, not whether an
individual notification was promoted. `push` is currently `false` on both platforms.

## Platform support

| Requirement or feature | iOS | Android |
|---|---|---|
| Package minimum OS | 18.0 | Android 10 (API 29) |
| Presentation | Lock Screen; Dynamic Island on supported devices | Ongoing progress notification |
| Optional enhanced presentation | Dynamic Island | Promoted Live Update when available and allowed |
| Permission | Live Activities enabled in system settings | Notifications enabled; runtime permission on Android 13+ |
| Local start, update, list, and end | Supported | Supported |
| Server-driven updates | Not included | Not included |

Requires PHP 8.4+, Laravel 12, and NativePHP Mobile 4.5+. The OS minimums above
match the package manifest, even where underlying native APIs support older versions.

The free content API supports a title, subtitle, numeric progress, a bundled
icon, and an accent color with a fixed native layout. Custom icons, countdowns,
ETA, and activity-specific deep links belong to the paid edition.
Local updates require your app to be executing; a visible
activity does not keep PHP running in the background.

## Need more features?

The [paid plugin](https://nativephp.com/plugins/vos/nativephp-live-activities)
builds on this original release with custom icons, countdowns and ETA,
activity-specific deep links, a fluent activity API, and more.
Check the listing and release notes for feature availability.

## Development

Run package tests with `composer test`. The original demo is in `examples/demo`.
The paid documentation site lives in `docs/`: run `npm ci` and
`npm run docs:build` to validate it, or `npm run docs:dev` to preview changes.

## License

The free edition is [MIT licensed](LICENSE). Bundled Lucide icons retain their
[third-party license notices](resources/icons/LICENSE).
