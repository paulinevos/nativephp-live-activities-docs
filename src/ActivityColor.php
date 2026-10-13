<?php

declare(strict_types=1);

namespace Vos\LiveActivities;

use Vos\LiveActivities\Exceptions\ActivityException;

final readonly class ActivityColor
{
    private function __construct(public string $hex) {}

    public static function hex(string $hex): self
    {
        if (preg_match('/^#[0-9a-fA-F]{6}$/D', $hex) !== 1) {
            throw ActivityException::invalid('Activity color must be a six-digit hex color such as #22C55E.');
        }

        return new self(strtoupper($hex));
    }
}
