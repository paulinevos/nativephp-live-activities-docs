<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Exceptions;

use RuntimeException;

final class ActivityException extends RuntimeException
{
    private function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public static function invalid(string $message): self
    {
        return new self('invalid_payload', $message);
    }

    public static function tooLarge(): self
    {
        return new self('payload_too_large', 'Activity attributes and content exceed 3800 UTF-8 bytes.');
    }

    public static function unavailable(): self
    {
        return new self('unavailable', 'Live Activities require a NativePHP runtime with this plugin installed.');
    }

    public static function native(string $code, string $message): self
    {
        return new self($code, $message);
    }

    public static function malformedResponse(): self
    {
        return new self('native_error', 'The native bridge returned an invalid Live Activities response.');
    }
}
