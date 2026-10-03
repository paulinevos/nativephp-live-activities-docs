# Configuration

Publish the optional application configuration:

```sh
php artisan vendor:publish --tag=live-activities-config
```

The plugin merges its defaults automatically. You only need to publish when
customizing settings. All settings live in `config/live-activities.php`:

```php
return [
    'icons' => [],
    'ios_icon_transition' => 'none',
    'ios_app_group' => null,
    'svg_converter' => [],
    'android' => [
        'channel_id' => 'live_activities',
        'channel_name' => 'Live activities',
    ],
];
```

## Registered icons

`icons` lists classes marked with `#[Icon]`. Classes must be instantiable without
required constructor arguments and expose a public, non-static `source()` method
returning `IconArtwork`. Optional `iosSource()` and `androidSource()` methods
choose platform-specific artwork. See [custom icons](/icons#custom-icons).

After adding or changing classes or their source artwork, run:

```sh
php artisan live-activities:prepare-icons
```

Commit `resources/live-activities/prepared`. The build checks that prepared assets
match the current source files and configuration. Regenerate stale assets before
rebuilding your native app. PNG preparation requires PHP GD; SVG validation
requires PHP DOM, and conversion needs Node.js and an SVG renderer.

## iOS icon transition

Set `ios_icon_transition` to `none` (default) or `fade`. A fade opts into
system-managed motion when an update replaces an icon; iOS controls playback timing.
This setting is applied to the widget during the native build, so rebuild after changing it.

## iOS App Group

`ios_app_group` defaults to `null`. Set it to your registered group identifier
(such as `group.com.example.delivery`) for [runtime artwork](/runtime-artwork).
Register the group for both the host app and widget extension, and rebuild.
See [iOS App Groups](/ios#app-groups-for-runtime-artwork).

## SVG converter

An empty `svg_converter` list selects the bundled Node/resvg adapter. Install
`@resvg/resvg-js` in your application to use it. A custom list specifies an
executable and fixed arguments, with the input path, output path, and pixel size
appended by the preparation command. See [SVG conversion](/icons#using-svg-source-artwork).

## Android notification channel

The `android.channel_id` and `android.channel_name` settings are **reserved**.
They mirror the native defaults; changing them currently does not reconfigure
the notification channel. The implementation uses the fixed `live_activities`
channel, named “Live activities”. See [Android behavior](/android).

## NativePHP deep-link settings

Activity links use NativePHP's `NATIVEPHP_DEEPLINK_SCHEME` or
`NATIVEPHP_DEEPLINK_HOST`. These belong to your app's NativePHP configuration,
not this plugin's configuration. See [deep links](/deep-links).
