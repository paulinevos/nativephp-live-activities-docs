<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Vos\DemoUI\DemoUIServiceProvider;
use Vos\LiveActivities\LiveActivitiesServiceProvider;

final class NativeServiceProvider extends ServiceProvider
{
    public function plugins(): array
    {
        return [LiveActivitiesServiceProvider::class, DemoUIServiceProvider::class];
    }
}
