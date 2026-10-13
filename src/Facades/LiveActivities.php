<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Facades;

use Illuminate\Support\Facades\Facade;
use Vos\LiveActivities\Activity;
use Vos\LiveActivities\ActivityCollection;
use Vos\LiveActivities\ActivityContent;
use Vos\LiveActivities\Capabilities;

/**
 * @method static Activity start(string $key, ActivityContent $content)
 * @method static Activity update(string $id, ActivityContent $content)
 * @method static void end(string $id)
 * @method static ActivityCollection all()
 * @method static Capabilities capabilities()
 */
final class LiveActivities extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Vos\LiveActivities\LiveActivities::class;
    }
}
