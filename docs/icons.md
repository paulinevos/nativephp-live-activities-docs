# Icons

The plugin comes with a set of [Lucide](https://lucide.dev/) icons baked in. You can also 
register your own, or use artwork prepared at runtime.

## Bundled icons

Pass a bundled Lucide icon instance:

```php
use Vos\LiveActivities\Icons\LucideCircleCheck;

$content = ActivityContent::make('Delivered')
    ->progress(1)
    ->icon(new LucideCircleCheck)
    ->color('#22C55E');
```

The bundled classes in `Vos\LiveActivities\Icons` are `LucideCircleDashed`,
`LucideUtensils`, `LucideTruck`, `LucideCircleCheck`, `LucidePackage`, `LucideClock`,
`LucideDownload`, and `LucideNavigation`. Their license notices ship in 
`resources/icons/LICENSE`.

Colors must use six-digit `#RRGGBB` hex notation. The default icon is
`LucideCircleDashed`, with white on iOS and the system's default notification accent
on Android. Updates replace the entire content, including icon and color, so
include them in each update to retain your styling.

On iOS, the accent colors the icon, progress bar, and percentage. On Android, it
sets the notification accent; status-bar icons stay monochrome and the system
controls their tint and final presentation.

## Custom icons

Create a class marked by an `#[Icon]` attribute:

```php
namespace App\Icons;

use Vos\LiveActivities\Attributes\Icon;
use Vos\LiveActivities\IconArtwork;

#[Icon]
final class DeliveryBike
{
    public function source(): IconArtwork
    {
        return IconArtwork::template(
            resource_path('path/to/image/delivery-bike.png'),
        );
    }
}
```

Publish the configuration, then list the icon classes to bundle:

```sh
php artisan vendor:publish --tag=live-activities-config
```

```php
// config/live-activities.php
'icons' => [
    App\Icons\DeliveryBike::class,
],
```

Prepare the platform sizes and commit the generated directory:

```sh
php artisan live-activities:prepare-icons
```

```php
use App\Icons\DeliveryBike;

$content = ActivityContent::make('On the way')
    ->icon(new DeliveryBike)
    ->color('#06B6D4');
```

Committing `resources/live-activities/prepared` lets builds reuse the prepared PNGs.

### Using SVG source artwork

Use an SVG source when you want to keep vector artwork in your application and
regenerate icon sizes from it.

To do this, install [resvg](https://github.com/thx/resvg-js) as a dev dependency:

```sh
npm install --save-dev @resvg/resvg-js
```

Return the SVG from your icon class:

```php
public function source(): IconArtwork
{
    return IconArtwork::template(
        resource_path('live-activities/icons/delivery-bike.svg'),
    );
}
```

To use a different renderer, override `svg_converter` with an executable plus its fixed
arguments. The preparation command appends the SVG input path, PNG output path,
and target pixel size, in that order. The renderer must write a PNG to that output
path; the arguments are passed directly without a shell.

```php
// config/live-activities.php
'icons' => [
    App\Icons\DeliveryBike::class,
],
'svg_converter' => [
    'node',
    base_path('path/to/renderer/prepare-icon.cjs'),
]
```

### Rendering modes

Choose a named `IconArtwork` constructor to preserve colors or draw a
single-color shape:

| Value | Appearance | Platforms |
|---|---|---|
| `IconArtwork::template($path)` | Transparent pixels stay invisible; the visible shape is drawn in one color. On iOS, `ActivityContent::color()` chooses that color. Android chooses the notification icon tint. | iOS and Android |
| `IconArtwork::original($path)` | Retains the source image's colors. The activity accent does not recolor the icon. | iOS only |

For template artwork, use a shape on a transparent background, such as a white bicycle
with the surrounding pixels transparent. The source color does not matter; the
transparency defines the shape. An opaque rectangular image becomes a solid
rectangle rather than retaining its details. This is what a **template mask**
means here.

Apple's [image rendering modes](https://developer.apple.com/documentation/swiftui/image/renderingmode(_:))
and Android's [notification icon guidance](https://developer.android.com/studio/write/image-asset-studio#notification)
provide the native background. The plugin requires template artwork on Android.

### Different artwork for iOS and Android

When omitted, `iosSource()` and `androidSource()` use the artwork returned by
`source()`. Define either optional public method when that operating system needs a different
image or rendering mode. These are ordinary PHP methods; no Swift or Kotlin is
required.

For example, keep a colorful brand logo on iOS and use a silhouette on Android:

```php
namespace App\Icons;

use Vos\LiveActivities\Attributes\Icon;
use Vos\LiveActivities\IconArtwork;

#[Icon]
final class BrandIcon
{
    public function source(): IconArtwork
    {
        return IconArtwork::original(resource_path('icons/brand.png'));
    }

    public function androidSource(): IconArtwork
    {
        return IconArtwork::template(resource_path('icons/brand-mask.png'));
    }
}
```

See [runtime artwork](/runtime-artwork) for images that are unavailable at build time, and [configuration](/configuration) for icon transitions and build settings.
