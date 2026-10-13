<?php

declare(strict_types=1);

namespace Vos\LiveActivities;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use Vos\LiveActivities\Exceptions\ActivityException;

/** @implements IteratorAggregate<int, Activity> */
final readonly class ActivityCollection implements Countable, IteratorAggregate
{
    /** @var list<Activity> */
    private array $activities;

    public function __construct(Activity ...$activities)
    {
        $ids = array_map(fn (Activity $activity): string => $activity->id, $activities);
        $keys = array_map(fn (Activity $activity): string => $activity->key, $activities);
        if (count(array_unique($ids)) !== count($ids) || count(array_unique($keys)) !== count($keys)) {
            throw ActivityException::invalid('Active activities must have unique ids and keys.');
        }
        $this->activities = array_values($activities);
    }

    public function count(): int
    {
        return count($this->activities);
    }

    /** @return Traversable<int, Activity> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->activities);
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(fn (Activity $activity): array => $activity->toArray(), $this->activities);
    }
}
