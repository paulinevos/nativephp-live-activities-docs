<?php

declare(strict_types=1);

namespace Vos\LiveActivities;

use Vos\LiveActivities\Exceptions\ActivityException;
use Vos\LiveActivities\Support\Payload;

final readonly class Activity
{
    private function __construct(public string $id, public string $key, public ActivityContent $content, public string $state) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        Payload::fields($payload, ['id', 'key', 'content', 'state']);
        if (! is_string($payload['id']) || ! is_string($payload['key']) || ! is_array($payload['content']) || $payload['state'] !== 'active') {
            throw ActivityException::invalid('An activity requires an id, key, content and active state.');
        }
        Payload::text($payload['id'], 'id');
        Payload::text($payload['key'], 'key', 80);
        $content = ActivityContent::fromArray($payload['content']);
        Payload::assertSize(['key' => $payload['key'], 'content' => $content->toArray()]);

        return new self($payload['id'], $payload['key'], $content, 'active');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'key' => $this->key, 'content' => $this->content->toArray(), 'state' => $this->state];
    }
}
