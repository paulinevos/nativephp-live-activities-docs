<?php

declare(strict_types=1);

namespace Vos\LiveActivities;

use Vos\LiveActivities\Exceptions\ActivityException;
use Vos\LiveActivities\Support\Payload;

final readonly class ActivityContent
{
    private function __construct(
        public string $title,
        public string $subtitle,
        public float $progress,
        public ?ActivityIcon $icon = null,
        public ?ActivityColor $color = null,
    ) {
        Payload::text($title, 'title', 120);
        Payload::text($subtitle, 'subtitle', 240, allowEmpty: true);
        if (! is_finite($progress) || $progress < 0 || $progress > 1) {
            throw ActivityException::invalid('progress must be finite and between 0 and 1.');
        }
    }

    public static function make(string $title): self
    {
        return new self($title, '', 0);
    }

    public function subtitle(string $subtitle): self
    {
        return new self($this->title, $subtitle, $this->progress, $this->icon, $this->color);
    }

    public function progress(float $progress): self
    {
        return new self($this->title, $this->subtitle, $progress, $this->icon, $this->color);
    }

    public function icon(ActivityIcon|string $icon): self
    {
        return new self($this->title, $this->subtitle, $this->progress,
            $icon instanceof ActivityIcon ? $icon : ActivityIcon::named($icon), $this->color);
    }

    public function color(ActivityColor|string $color): self
    {
        return new self($this->title, $this->subtitle, $this->progress,
            $this->icon, $color instanceof ActivityColor ? $color : ActivityColor::hex($color));
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        Payload::fields($payload, ['title', 'subtitle', 'progress'], ['icon', 'color']);
        if (! is_string($payload['title']) || ! is_string($payload['subtitle']) || (! is_int($payload['progress']) && ! is_float($payload['progress']))) {
            throw ActivityException::invalid('Content requires string title/subtitle and numeric progress.');
        }

        self::assertPresentation($payload);

        return new self($payload['title'], $payload['subtitle'], $payload['progress'],
            isset($payload['icon']) ? ActivityIcon::named($payload['icon']) : null,
            isset($payload['color']) ? ActivityColor::hex($payload['color']) : null);
    }

    /** @param array<string, mixed> $payload */
    private static function assertPresentation(array $payload): void
    {
        foreach (['icon', 'color'] as $field) {
            self::assertOptionalString($payload, $field);
        }
    }

    /** @param array<string, mixed> $payload */
    private static function assertOptionalString(array $payload, string $field): void
    {
        if (array_key_exists($field, $payload) && ! is_string($payload[$field])) {
            throw ActivityException::invalid('Activity '.$field.' must be a string.');
        }
    }

    /** @return array{title: string, subtitle: string, progress: float, icon?: string, color?: string} */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title, 'subtitle' => $this->subtitle, 'progress' => $this->progress,
            'icon' => $this->icon?->value, 'color' => $this->color?->hex,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
