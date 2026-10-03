# Errors and recovery

Invalid input and failed native operations throw
`Vos\LiveActivities\Exceptions\ActivityException`. Its readonly `errorCode`
property carries the stable error code; the exception message explains the failure.

| Code | Meaning / next step |
|---|---|
| `invalid_payload` | Correct invalid text, progress, colors, links, or artwork |
| `payload_too_large` | Reduce the complete activity payload below 3800 UTF-8 bytes |
| `invalid_state` | Use the fluent API in order: pending → active → ended |
| `unavailable` | Check NativePHP runtime, plugin registration, and native build |
| `permission_denied` | Check activity/notification permission and settings |
| `activity_not_found` | The activity may have ended or been dismissed; list current activities |
| `duplicate_activity` | Choose another key or recover the existing active activity |
| `native_error` | Inspect the message; native execution or its response failed |

Failed fluent operations preserve the previous local state. This does not
necessarily prove a native operation was rolled back: for example, an iOS timeout
can leave its outcome uncertain. List activities before retrying, and avoid blindly
starting a second activity with the same key.

## Check availability

Outside NativePHP, `LiveActivities::capabilities()` returns unsupported flags and
mutations throw `unavailable`. If the app runs on-device but reports unavailable,
verify that the plugin is registered and that the native app has been rebuilt
since installation.

## Icon preparation failures

For stale or missing prepared assets, run `live-activities:prepare-icons` after
changing registered icons and commit its output. Ensure PHP GD is available.
For SVGs, also check PHP DOM, Node.js, and `@resvg/resvg-js` or your custom renderer.

Opaque template artwork renders as a solid rectangle: the shape is defined by
transparency. See [rendering modes](/icons#rendering-modes).

For runtime artwork on iOS, verify the configured App Group and the provisioning
of both host and widget. Inputs must be valid readable local PNGs within the
[documented limits](/runtime-artwork#input-requirements).
