<?php

declare(strict_types=1);

namespace Vos\LiveActivities;

use Vos\LiveActivities\Exceptions\ActivityException;
use Vos\LiveActivities\Support\Payload;

final readonly class Capabilities
{
    private function __construct(public string $platform, public bool $supported, public bool $enabled, public bool $push, public bool $promoted) {}

    public static function unsupported(): self
    {
        return new self('unsupported', false, false, false, false);
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        Payload::fields($payload, ['platform', 'supported', 'enabled', 'push', 'promoted']);
        if (! in_array($payload['platform'], ['ios', 'android', 'unsupported'], true)) {
            throw ActivityException::invalid('Invalid activity platform.');
        }
        foreach (['supported', 'enabled', 'push', 'promoted'] as $field) {
            self::assertBoolean($payload[$field]);
        }
        if ($payload['push']) {
            throw ActivityException::invalid('Push updates are not supported by contract version 1.');
        }

        return new self(...$payload);
    }

    // Mixed is intentional at the untrusted native JSON boundary.
    private static function assertBoolean(mixed $value): void
    {
        if (! is_bool($value)) {
            throw ActivityException::invalid('Activity capabilities must be booleans.');
        }
    }
}
