<?php

declare(strict_types=1);

namespace Vos\LiveActivities;

use Vos\LiveActivities\Bridge\NativeBridge;
use Vos\LiveActivities\Exceptions\ActivityException;
use Vos\LiveActivities\Support\Payload;

final readonly class LiveActivities
{
    public function __construct(private NativeBridge $bridge) {}

    public function start(string $key, ActivityContent $content): Activity
    {
        Payload::text($key, 'key', 80);
        $payload = ['key' => $key, 'content' => $content->toArray()];
        Payload::assertSize($payload);

        return $this->activity($this->bridge->call('Start', $payload));
    }

    public function update(string $id, ActivityContent $content): Activity
    {
        Payload::text($id, 'id');

        // Native update validates the complete attributes/content size with its stored key.
        return $this->activity($this->bridge->call('Update', ['id' => $id, 'content' => $content->toArray()]));
    }

    public function end(string $id): void
    {
        Payload::text($id, 'id');
        $payload = $this->bridge->call('End', ['id' => $id]);
        Payload::fields($payload, ['id', 'state']);
        if ($payload['id'] !== $id || $payload['state'] !== 'ended') {
            throw ActivityException::malformedResponse();
        }
    }

    public function all(): ActivityCollection
    {
        $payload = $this->bridge->call('List');
        Payload::fields($payload, ['activities']);
        if (! is_array($payload['activities']) || ! array_is_list($payload['activities'])) {
            throw ActivityException::malformedResponse();
        }

        return new ActivityCollection(...array_map($this->parseActivity(...), $payload['activities']));
    }

    public function capabilities(): Capabilities
    {
        if (! $this->bridge->available()) {
            return Capabilities::unsupported();
        }

        return Capabilities::fromArray($this->bridge->call('Capabilities'));
    }

    /** @param array<string, mixed> $payload */
    private function activity(array $payload): Activity
    {
        Payload::fields($payload, ['activity']);

        return $this->parseActivity($payload['activity']);
    }

    // Mixed is intentional at the untrusted native JSON boundary.
    private function parseActivity(mixed $payload): Activity
    {
        if (! is_array($payload)) {
            throw ActivityException::malformedResponse();
        }

        return Activity::fromArray($payload);
    }
}
