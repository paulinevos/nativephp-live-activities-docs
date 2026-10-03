# Android behavior

The package requires Android 10 (API 29)+. Its baseline presentation is an ongoing
progress notification. On supported systems, it also requests promotion to a Live
Update, potentially including a status-bar chip.

## Notification permission

On Android 13+, your app must request `POST_NOTIFICATIONS` runtime permission
before starting an activity. The plugin declares notification permissions in its
manifest, but **does not show permission prompts**.

Disabled application notifications or a blocked “Live activities” notification
channel cause `permission_denied`; the plugin does not report a successful start
when notifications cannot be shown. [Capability checks](/capabilities) let you
check whether notifications are currently enabled.

## Promotion and fallback

Promotion depends on available platform APIs and the user's settings. When
promotion is unavailable or disabled, the plugin uses an ordinary ongoing
progress notification. This fallback does not bypass notification permission.

On API 36+, runtime detection checks for the promotion builder API and whether
the system allows promoted notifications. Some APIs arrive in minor SDK releases,
so the Android major version alone does not establish availability.

`Capabilities::promoted` means promotion can be **requested**, not that a
particular activity was promoted. The system remains authoritative over the
final appearance. See [Android's Live Updates requirements](https://developer.android.com/develop/ui/views/notifications/live-update).

## Icons and colors

Android notification icons are monochrome. Use [template artwork](/icons#rendering-modes)
with a transparent background. A full-color iOS image needs a separate Android
mask. The accent color requests the notification accent; Android controls small
icon tint and the final presentation.

## Lifecycle and persistence

Native IDs, keys, and content are persisted across app-process restarts while the
notifications remain active. Listing and mutation reconcile stored metadata with
the system's currently active notifications. Dismissal, force-stop, or reboot
must not cause a later update to repost an old activity.

The notification uses its activity's [deep link](/deep-links) when supplied;
otherwise it opens the app. Tapping does not end it. Separate activities have
separate tap intents, even if they share the same destination.

No foreground service or custom notification layout is used. A visible activity
does not keep PHP executing in the background. Server-driven updates and
lifecycle events are not currently implemented.

## Notification channel settings

The native implementation currently uses the fixed `live_activities` channel.
The PHP channel configuration keys are reserved and do not change this channel.
See [configuration](/configuration#android-notification-channel).
