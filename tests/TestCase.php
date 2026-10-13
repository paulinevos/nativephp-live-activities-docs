<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Vos\LiveActivities\LiveActivitiesServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LiveActivitiesServiceProvider::class];
    }
}
