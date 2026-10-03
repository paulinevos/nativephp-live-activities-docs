# iOS setup

The package requires iOS 18+. Activities appear on the Lock Screen and, on
supported devices, in the Dynamic Island. Live Activities must be enabled in
system settings. [Check capabilities](/capabilities) before offering the feature.

## Widget extension installation

The plugin's post-compile hook installs the widget extension automatically when
you rebuild a registered plugin. You do not need to create a separate Swift package,
install CocoaPods dependencies, or maintain a demo-specific widget.

The generated extension bundle identifier is `<app-id>.liveactivities`. The
extension inherits the host app's development team, signing identity, app version,
and build number. Repeated builds refresh the generated widget sources and settings.

## Signing and provisioning

Simulator builds need no Apple developer account. Signed device builds need
provisioning for both the host application and its widget extension. Make sure
your development team can provision the extension's identifier as well as the host's.

Building requires macOS with Xcode and its command-line tools selected. If the
hook reports a missing native project or invalid app ID, fix the host project's
NativePHP configuration before rebuilding.

## App Groups for runtime artwork

Bundled and registered build-time icons do not require an App Group. Artwork
prepared at runtime needs a shared container accessible to both the host and widget.

1. Register an App Group in your Apple developer account.
2. Enable it for both the host app ID and `<app-id>.liveactivities` extension app ID.
3. Set its identifier in `config/live-activities.php`:

```php
'ios_app_group' => 'group.com.example.delivery',
```

4. Ensure the provisioning profiles for both targets include the group.
5. Rebuild your native app in your terminal.

See [runtime artwork](/runtime-artwork) for preparation and retention behavior.

## Updates and dismissal

Local updates require your app's PHP runtime to be executing. An activity can
remain visible after the app backgrounds, but it does not keep PHP running.
There are currently no push updates, push tokens, or lifecycle events.

`LiveActivity::all()` reads ActivityKit's current activities, including stale ones,
while excluding ended or dismissed activities. Ending removes the presentation
immediately. A successful update confirms readable native content, not that iOS
has displayed a frame. If an operation times out, list activities before retrying:
the native outcome may be uncertain.

For per-activity tap destinations, see [deep links](/deep-links). For color,
full-color artwork, and optional icon fades, see [icons](/icons) and
[configuration](/configuration#ios-icon-transition).
