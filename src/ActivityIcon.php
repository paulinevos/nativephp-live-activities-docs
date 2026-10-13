<?php

declare(strict_types=1);

namespace Vos\LiveActivities;

use Vos\LiveActivities\Exceptions\ActivityException;

enum ActivityIcon: string
{
    case CircleDashed = 'circle-dashed';
    case Utensils = 'utensils';
    case Truck = 'truck';
    case CircleCheck = 'circle-check';
    case Package = 'package';
    case Clock = 'clock';
    case Download = 'download';
    case Navigation = 'navigation';

    public static function named(string $name): self
    {
        return self::tryFrom($name) ?? throw ActivityException::invalid('Unknown activity icon: '.$name.'.');
    }
}
