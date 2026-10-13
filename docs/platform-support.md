# Platform support

| Requirement or feature | iOS | Android |
|---|---|---|
| Package minimum OS | 18.0 | Android 10 (API 29) |
| Presentation | Lock Screen; Dynamic Island on supported devices | Ongoing progress notification |
| Optional enhanced presentation | Dynamic Island | Promoted Live Update when available and allowed |
| Permission | Live Activities enabled in system settings | Notifications enabled; runtime permission on Android 13+ |
| Local start, update, list, and end | Supported | Supported |
| Activity-specific tap destinations | Supported | Supported |
| [Countdown](/php-api#eta-and-countdowns) | Ticks to zero, then holds | Native notification timer; continues below zero |
| [ETA](/php-api#eta-and-countdowns) | Local finish time | Local finish time |
| Server-driven updates | Planned | Planned |

Requires PHP 8.4+, Laravel 12, and NativePHP Mobile 4.5+. The OS minimums above
match the package manifest, even where underlying native APIs support older versions.

The content API supports a title, subtitle, numeric progress, bundled or custom
icons, an accent color, and a countdown or ETA with a fixed native layout. See
[version support](/php-api#eta-and-countdowns) before using the time builders.
Larger image areas are planned.

Countdowns tick without per-second updates from your app, but they do not end the
activity automatically. Sending new content or ending an activity requires your
app to be executing; a visible activity does not keep PHP running in the background.

See [iOS setup](/ios), [Android behavior](/android), and [capability checks](/capabilities).
