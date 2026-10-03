# Runtime artwork

Use runtime artwork when the image depends on the activity and is unavailable
at build time: for example, a merchant logo downloaded for a delivery, or album
art downloaded for a track. The image occupies the existing icon slot; it does
not change the activity layout. For full-color artwork on iOS, use `original`
rendering and provide a template mask as a fallback for Android.

Persist a local PNG before starting or
updating an activity:

```php
$icon = LiveActivities::prepareIcon(storage_path('app/delivery.png'));
$content = ActivityContent::make('On the way')->icon($icon);

// Original iOS colors with a separate Android mask:
$brand = LiveActivities::prepareIcon(
    storage_path('app/brand.png'),
    rendering: 'original',
    androidPath: storage_path('app/brand-mask.png'),
);
```

Runtime preparation on iOS requires `ios_app_group` in the configuration and an
App Group registered for both the host app and widget extension in your Apple
developer account. Rebuild after configuring it. Unreferenced prepared artwork
expires after 24 hours; artwork used by visible activities is retained.

## Input requirements

`prepareIcon()` accepts a readable local PNG, at most 2 MiB and 4096 pixels per
dimension. It does not download images. Download and persist remote artwork in
your application first. SVG conversion belongs to [build-time preparation](/icons#using-svg-source-artwork).

The return value is a `CustomIcon` reference for `ActivityContent::icon()`.
Prepare once and reuse it in content updates while needed. The native platform
manages prepared storage; source image bytes do not travel in the activity payload.

See [iOS App Groups](/ios#app-groups-for-runtime-artwork) before using this feature on iOS.
