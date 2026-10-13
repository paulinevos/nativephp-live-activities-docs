# Getting started

Start, update, and end iOS Live Activities and Android ongoing notifications from PHP.

Requires PHP 8.4+, Laravel 12, and NativePHP Mobile 4.5+.
See [platform support](/platform-support) for OS requirements and permissions.

In an existing NativePHP Mobile app:

```sh
composer require vos/nativephp-live-activities
php artisan vendor:publish --tag=nativephp-plugins-provider
php artisan native:plugin:register vos/nativephp-live-activities
php artisan native:plugin:list
```

Remember to configure your NativePHP Composer
repository and purchase credentials before running `composer require`.

Registration adds `Vos\LiveActivities\LiveActivitiesServiceProvider` to your
app's `App\Providers\NativeServiceProvider::plugins()` list. Then run the matching command in your terminal for your chosen platform:

```sh
php artisan native:run ios
# Or:
php artisan native:run android
```

The build hook installs the iOS widget extension automatically. Signed iOS device
builds need provisioning for both the host app and its extension; simulator builds
need no Apple developer account. On Android 13+, your app must request notification
permission before starting an activity. This plugin does not display permission prompts.

## Start your first activity

```php
use Vos\LiveActivities\ActivityContent;
use Vos\LiveActivities\Icons\LucideUtensils;
use Vos\LiveActivities\Icons\LucideTruck;
use Vos\LiveActivities\Facades\LiveActivities;
use Vos\LiveActivities\LiveActivity;

$activity = LiveActivity::make('delivery-42',
    ActivityContent::make('Preparing your order')->subtitle('In the kitchen')
        ->icon(new LucideUtensils)->color('#F97316')
);
$activity->start();

$activity->update(
    ActivityContent::make('On the way')->progress(0.5)
        ->icon(new LucideTruck)->color('#06B6D4')
);

LiveActivity::all();
LiveActivities::capabilities();
$activity->end();
```

Give each activity a unique key. Call `start()` to show it, `update()` to replace
what it shows, and `end()` to remove it.
Content builders are immutable, and progress ranges from `0.0` to `1.0`.

Continue with the [PHP API](/php-api), [ETA and countdowns](/php-api#eta-and-countdowns),
[custom icons](/icons#custom-icons), or [deep links](/deep-links).
See [iOS setup](/ios) and [Android permissions](/android#notification-permission) for platform details.
