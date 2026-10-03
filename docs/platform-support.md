# Platform support

| Requirement or feature | iOS | Android |
|---|---|---|
| Package minimum OS | 18.0 | Android 10 (API 29) |
| Presentation | Lock Screen; Dynamic Island on supported devices | Ongoing progress notification |
| Optional enhanced presentation | Dynamic Island | Promoted Live Update when available and allowed |
| Permission | Live Activities enabled in system settings | Notifications enabled; runtime permission on Android 13+ |
| Local start, update, list, and end | Supported | Supported |
| Activity-specific tap destinations | Supported | Supported |
| Server-driven updates | Planned | Planned |

Requires PHP 8.4+, Laravel 12, and NativePHP Mobile 4.5+. The OS minimums above
match the package manifest, even where underlying native APIs support older versions.

The current content API supports a title, subtitle, numeric progress, bundled or custom
icons, and an accent color with a fixed native layout. Larger image areas and timers
are planned. Local updates require your app to be executing; a visible
activity does not keep PHP running in the background.

See [iOS setup](/ios), [Android behavior](/android), and [capability checks](/capabilities).
